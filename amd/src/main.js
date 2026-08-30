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

const OPEN_MODAL_SELECTOR = '[data-fastlearning-open="modal"]';

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
 * Initialise the block resource links.
 */
export const init = () => {
    document.addEventListener('click', (event) => {
        const link = event.target.closest(OPEN_MODAL_SELECTOR);
        if (!link) {
            return;
        }

        event.preventDefault();
        openResourceModal(link);
    });
};
