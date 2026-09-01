// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Main block JS controller.
 *
 * @module     block_fastlearning/main
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

const OPEN_MODAL_SELECTOR = '[data-fastlearning-open="modal"]';
const ITEM_SELECTOR = '[data-fastlearning-remaining]';
const MILLISECONDS_IN_SEC = 1000;

// Guard against attaching the delegated modal listener more than once per page.
let modalListenerAttached = false;

/**
 * Build the iframe URL, flagging the target page as embedded.
 *
 * @param {String} href Original resource URL.
 * @return {String} URL with the inpopup marker appended.
 */
const buildIframeUrl = (href) => href + (href.indexOf('?') === -1 ? '?' : '&') + 'inpopup=1';

/**
 * Open a resource link inside a Moodle modal iframe.
 *
 * @param {HTMLElement} link The clicked resource link.
 */
const openResourceModal = async(link) => {
    const href = link.getAttribute('href');
    if (!href) {
        return;
    }

    const iframe = document.createElement('iframe');
    iframe.className = 'block-fastlearning-modal-iframe';
    iframe.src = buildIframeUrl(href);
    iframe.style.width = '100%';
    iframe.style.height = '70vh';
    iframe.style.border = '0';

    const modal = await Modal.create({
        title: link.getAttribute('title') || link.textContent.trim(),
        large: true,
        scrollable: true,
        removeOnClose: true,
        show: true,
    });

    modal.setBody(iframe.outerHTML);

    // Stop any media inside the iframe once the modal is dismissed.
    modal.getRoot().on(ModalEvents.hidden, () => {
        const modaliframe = modal.getBody()[0].querySelector('iframe');
        modaliframe?.contentDocument?.querySelectorAll('video, audio').forEach((media) => media.pause());
    });
};

/**
 * Format a signed number of seconds as the same "[-]HH:MM:SS" string used server-side.
 *
 * @param {Number} seconds Signed remaining seconds.
 * @return {String} Formatted remaining/overdue time.
 */
const formatRemaining = (seconds) => {
    const abs = Math.abs(seconds);
    const hours = Math.floor(abs / 3600);
    const minutes = Math.floor((abs % 3600) / 60);
    const secs = Math.floor(abs % 60);
    const pad = (value) => String(value).padStart(2, '0');

    return (seconds < 0 ? '-' : '') + `${pad(hours)}:${pad(minutes)}:${pad(secs)}`;
};

/**
 * Read the countdown state from the resource elements currently in the container.
 *
 * @param {HTMLElement} container The block instance container.
 * @return {Array} List of {element, seconds} being tracked.
 */
const collectClocks = (container) => {
    return Array.from(container.querySelectorAll(ITEM_SELECTOR)).map((element) => ({
        element,
        seconds: parseInt(element.getAttribute('data-seconds'), 10) || 0,
    }));
};

/**
 * Apply the overdue/pending styling for a single clock element.
 *
 * @param {HTMLElement} element The remaining time element.
 * @param {Boolean} isoverdue Whether the deadline has passed.
 */
const applyOverdueClass = (element, isoverdue) => {
    element.classList.toggle('text-danger', isoverdue);
    element.classList.toggle('text-success', !isoverdue);
};

/**
 * Create a controller that manages the per-second countdown for a block instance.
 *
 * @param {HTMLElement} container The block instance container.
 * @return {Object} Controller with start/stop/resync methods.
 */
const createClock = (container) => {
    let clocks = collectClocks(container);
    let intervalid = null;

    const tick = () => {
        clocks.forEach((clock) => {
            clock.seconds -= 1;
            clock.element.textContent = formatRemaining(clock.seconds);
            applyOverdueClass(clock.element, clock.seconds < 0);
        });
    };

    return {
        start: () => {
            if (!intervalid) {
                intervalid = window.setInterval(tick, MILLISECONDS_IN_SEC);
            }
        },
        stop: () => {
            if (intervalid) {
                window.clearInterval(intervalid);
                intervalid = null;
            }
        },
        // Re-read the clocks from the DOM, used after the container content has been replaced.
        resync: () => {
            clocks = collectClocks(container);
        },
    };
};

/**
 * Fetch the pending resources from the server and replace the container content.
 *
 * @param {HTMLElement} container The block instance container.
 * @param {Number} courseid Course id shown in the block, or SITEID.
 * @param {Object} clock The clock controller to resync after the DOM is replaced.
 * @return {Promise} Resolves once the container has been refreshed.
 */
const refresh = (container, courseid, clock) => {
    const request = Ajax.call([{
        methodname: 'block_fastlearning_get_pending_resources',
        args: {courseid},
    }])[0];

    return request
        .then((data) => Templates.renderForPromise('block_fastlearning/block_content', data))
        .then(({html, js}) => {
            Templates.replaceNodeContents(container, html, js);
            clock.resync();
            return null;
        })
        .catch(Notification.exception);
};

/**
 * Create a controller that polls the server for updated pending resources.
 *
 * Pauses while the tab is hidden and refreshes immediately when it becomes visible again,
 * following the same periodic-refresh idea used by core/backoff_timer elsewhere in Moodle.
 *
 * @param {HTMLElement} container The block instance container.
 * @param {Number} courseid Course id shown in the block, or SITEID.
 * @param {Number} refreshinterval Seconds between refreshes, 0 disables polling.
 * @param {Object} clock The clock controller to resync after each refresh.
 * @return {Object} Controller with a start method.
 */
const createPoller = (container, courseid, refreshinterval, clock) => {
    let intervalid = null;

    const startInterval = () => {
        if (!intervalid) {
            intervalid = window.setInterval(() => refresh(container, courseid, clock), refreshinterval * MILLISECONDS_IN_SEC);
        }
    };

    const stopInterval = () => {
        if (intervalid) {
            window.clearInterval(intervalid);
            intervalid = null;
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clock.stop();
            stopInterval();
        } else {
            clock.start();
            refresh(container, courseid, clock).then(() => startInterval()).catch(() => {
                startInterval();
            });
        }
    });

    return {
        start: () => startInterval(),
    };
};

/**
 * Initialise the delegated click handler used to open resources in a modal, once per page.
 */
const initModalHandler = () => {
    if (modalListenerAttached) {
        return;
    }
    modalListenerAttached = true;

    document.addEventListener('click', (event) => {
        const link = event.target.closest(OPEN_MODAL_SELECTOR);
        if (!link) {
            return;
        }

        event.preventDefault();
        openResourceModal(link);
    });
};

/**
 * Initialise a Fast learning block instance.
 *
 * @param {Object} params Block instance parameters.
 * @param {String} params.containerid Id of the block instance container.
 * @param {Number} params.courseid Course id shown in the block, or SITEID.
 * @param {Number} params.refreshinterval Seconds between AJAX refreshes, 0 disables polling.
 */
export const init = (params) => {
    initModalHandler();

    const container = document.getElementById(params.containerid);
    if (!container) {
        return;
    }

    const clock = createClock(container);
    clock.start();

    if (params.refreshinterval > 0) {
        createPoller(container, params.courseid, params.refreshinterval, clock).start();
    }
};
