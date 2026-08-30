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
 * Urgency-level management interface.
 *
 * @module     block_fastlearning/levels
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalEvents from 'core/modal_events';
import ModalFactory from 'core/modal_factory';
import ModalSaveCancel from 'core/modal_save_cancel';
import Notification from 'core/notification';
import SortableList from 'core/sortable_list';
import Templates from 'core/templates';
import Y from 'core/yui';
import {add as addToast} from 'core/toast';
import {getString} from 'core/str';

const ROOT_SELECTOR = '#block-fastlearning-levels';

let root;
let levels;
let icons;

/**
 * Update the empty-state visibility.
 */
const updateEmptyState = () => {
    root.querySelector('[data-region="empty-message"]').classList.toggle('d-none', levels.length > 0);
};

/**
 * Render or replace one level in the list.
 *
 * @param {Object} level Saved level.
 */
const renderLevel = async(level) => {
    const {html, js} = await Templates.renderForPromise('block_fastlearning/level_item', level);
    const current = root.querySelector(`[data-level-id="${level.id}"]`);

    if (current) {
        Templates.replaceNode(current, html, js);
    } else {
        Templates.appendNodeContents(root.querySelector('[data-region="levels-list"]'), html, js);
    }
    updateEmptyState();
};

/**
 * Restore the DOM order represented by the local state.
 */
const restoreOrder = () => {
    const list = root.querySelector('[data-region="levels-list"]');
    levels.forEach((level) => list.append(root.querySelector(`[data-level-id="${level.id}"]`)));
};

/**
 * Save the current DOM order over AJAX.
 */
const saveOrder = async() => {
    const ids = Array.from(root.querySelectorAll('[data-region="levels-list"] > [data-level-id]:not(.sortable-list-is-dragged)'))
        .map((element) => Number.parseInt(element.dataset.levelId));

    try {
        await Ajax.call([{
            methodname: 'block_fastlearning_reorder_levels',
            args: {ids},
        }])[0];
        levels = ids.map((id, priority) => ({...levels.find((level) => Number(level.id) === id), urgencylevel: priority}));
        addToast(getString('levelsreordered', 'block_fastlearning'), {type: 'success'});
    } catch (error) {
        restoreOrder();
        Notification.exception(error);
    }
};

/**
 * Configure the searchable icon selector in a level modal.
 *
 * @param {HTMLElement} modalRoot Modal root element.
 */
const configureIconPicker = (modalRoot) => {
    const options = modalRoot.querySelector('[data-region="icon-options"]');
    const toggle = modalRoot.querySelector('[data-action="toggle-icons"]');

    toggle.addEventListener('click', () => {
        const opening = options.classList.contains('d-none');
        options.classList.toggle('d-none', !opening);
        toggle.setAttribute('aria-expanded', opening.toString());
        if (opening) {
            modalRoot.querySelector('[data-action="filter-icons"]').focus();
        }
    });

    modalRoot.querySelector('[data-action="filter-icons"]').addEventListener('input', (event) => {
        const search = event.target.value.trim().toLowerCase();
        modalRoot.querySelectorAll('[data-action="select-icon"]').forEach((button) => {
            button.classList.toggle('d-none', !button.dataset.icon.toLowerCase().includes(search));
        });
    });

    modalRoot.querySelectorAll('[data-action="select-icon"]').forEach((button) => {
        button.addEventListener('click', () => {
            modalRoot.querySelector('input[name="icon"]').value = button.dataset.icon;
            modalRoot.querySelector('[data-region="selected-icon"]').innerHTML = button.innerHTML;
            modalRoot.querySelectorAll('[data-action="select-icon"]').forEach((option) => {
                option.classList.toggle('active', option === button);
            });
            options.classList.add('d-none');
            toggle.setAttribute('aria-expanded', 'false');
        });
    });
};

/**
 * Open the create/edit modal.
 *
 * @param {Number} id Existing record id, or zero for a new level.
 */
const openLevelModal = async(id = 0) => {
    const existing = levels.find((level) => Number(level.id) === id);
    const defaultIcon = icons.some((icon) => icon.identifier === 'core:i/warning') ? 'core:i/warning' : icons[0].identifier;
    const level = existing || {id: 0, name: '', minutes: 0, color: '#dc3545', icon: defaultIcon};
    const formIcons = icons.map((icon) => ({...icon, selected: icon.identifier === level.icon}));
    const selectedIcon = formIcons.find((icon) => icon.selected);
    const {html} = await Templates.renderForPromise('block_fastlearning/level_form', {
        ...level,
        icons: formIcons,
        selectediconhtml: selectedIcon ? selectedIcon.html : '',
    });
    const modal = await ModalFactory.create({
        type: ModalSaveCancel.TYPE,
        title: getString(existing ? 'editlevel' : 'addlevel', 'block_fastlearning'),
        body: html,
        large: true,
        removeOnClose: true,
    });
    const modalRoot = modal.getRoot()[0];
    const form = modalRoot.querySelector('[data-region="level-form"]');

    configureIconPicker(modalRoot);

    modal.getRoot().on(ModalEvents.save, async(event) => {
        event.preventDefault();
        if (!form.reportValidity()) {
            return;
        }

        const saveButton = modal.getFooter().find('[data-action="save"]');
        const data = new FormData(form);
        saveButton.prop('disabled', true);

        try {
            const saved = await Ajax.call([{
                methodname: 'block_fastlearning_save_level',
                args: {
                    id: Number.parseInt(data.get('id')),
                    name: data.get('name'),
                    minutes: Number.parseInt(data.get('minutes')),
                    color: data.get('color'),
                    icon: data.get('icon'),
                },
            }])[0];
            const index = levels.findIndex((candidate) => Number(candidate.id) === Number(saved.id));
            if (index === -1) {
                levels.push(saved);
            } else {
                levels[index] = saved;
            }
            await renderLevel(saved);
            modal.destroy();
            addToast(getString('levelsaved', 'block_fastlearning'), {type: 'success'});
        } catch (error) {
            saveButton.prop('disabled', false);
            Notification.exception(error);
        }
    });

    await modal.show();
    M.util.init_colour_picker(Y, 'fastlearning-level-color', null);
};

/**
 * Confirm and delete an urgency level.
 *
 * @param {Number} id Existing record id.
 */
const openDeleteModal = async(id) => {
    const level = levels.find((candidate) => Number(candidate.id) === id);
    if (!level) {
        return;
    }

    const [title, body, deleteLabel, cancelLabel] = await Promise.all([
        getString('deletelevel', 'block_fastlearning'),
        getString('deletelevelconfirm', 'block_fastlearning', level.name),
        getString('delete', 'core'),
        getString('cancel', 'core'),
    ]);
    const modal = await ModalFactory.create({
        type: ModalFactory.types.DELETE_CANCEL,
        title,
        body,
        buttons: {delete: deleteLabel, cancel: cancelLabel},
        removeOnClose: true,
    });

    modal.getRoot().on(ModalEvents.delete, async(event) => {
        event.preventDefault();
        const deleteButton = modal.getFooter().find('[data-action="delete"]');
        deleteButton.prop('disabled', true);

        try {
            await Ajax.call([{
                methodname: 'block_fastlearning_delete_level',
                args: {id},
            }])[0];
            levels = levels
                .filter((candidate) => Number(candidate.id) !== id)
                .map((candidate, priority) => ({...candidate, urgencylevel: priority}));
            root.querySelector(`[data-level-id="${id}"]`).remove();
            updateEmptyState();
            modal.destroy();
            addToast(getString('leveldeleted', 'block_fastlearning'), {type: 'success'});
        } catch (error) {
            deleteButton.prop('disabled', false);
            Notification.exception(error);
        }
    });

    await modal.show();
};

/**
 * Set up actions and Moodle's accessible sortable list.
 */
const configureManager = () => {
    root.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-action="add-level"]');
        const editButton = event.target.closest('[data-action="edit-level"]');
        const deleteButton = event.target.closest('[data-action="delete-level"]');
        if (addButton) {
            openLevelModal().catch(Notification.exception);
        } else if (editButton) {
            openLevelModal(Number.parseInt(editButton.dataset.levelId)).catch(Notification.exception);
        } else if (deleteButton) {
            openDeleteModal(Number.parseInt(deleteButton.dataset.levelId)).catch(Notification.exception);
        }
    });

    const list = root.querySelector('[data-region="levels-list"]');
    new SortableList(list);
    list.addEventListener(SortableList.EVENTS.elementDrop, (event) => {
        if (event.detail.positionChanged) {
            saveOrder();
        }
    });
};

/**
 * Initialise the urgency-level manager.
 *
 * @param {Object} config Initial levels and available Moodle icons.
 */
export const init = async(config) => {
    root = document.querySelector(ROOT_SELECTOR);

    levels = [];
    root.querySelectorAll('.list-group-item').forEach((element) => {
        let level = {
            id: Number.parseInt(element.dataset.levelId),
            name: element.dataset.name,
            minutes: Number.parseInt(element.dataset.minutes),
            color: element.dataset.color,
            icon: element.dataset.icon,
        };
        levels.push(level);
    });

    icons = config.icons;
    configureManager();
};
