// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Two-step resource assignment form.
 *
 * @module     block_fastlearning/manage_wizard
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const ROOT = '#block-fastlearning-ra-wizard';

let root;
let config;
let userRequest = 0;
let moduleRequest = 0;

const field = (name) => root.querySelector(`[name="${name}"]`);

const regions = {
    userid: 'user-selector',
    modulecourseid: 'course-selector',
    cmid: 'cm-selector',
};

const emptyLists = {
    userid: 'users',
    modulecourseid: 'courses',
    cmid: 'coursemodules',
};

const clearField = (element) => {
    if (element?.value) {
        element.value = '';
        element.dispatchEvent(new Event('change'));
    }
};

const setAvailability = (name, available) => {
    root.querySelector(`[data-region="${regions[name]}"]`).classList.toggle('d-none', !available);
    root.querySelector(`[data-empty-list="${emptyLists[name]}"]`)?.classList.toggle('d-none', available);
};

const showStep = (step) => {
    root.querySelectorAll('[data-region="wizard-step"]').forEach((section) => {
        section.classList.toggle('d-none', Number(section.dataset.step) !== step);
    });
    root.querySelectorAll('[data-region="wizard-progress-step"]').forEach((indicator) => {
        indicator.classList.toggle('active', Number(indicator.dataset.step) === step);
    });
    root.querySelector(`[data-region="wizard-step"][data-step="${step}"]`)
        .querySelector('input, select, button')?.focus();
};

const validateFirstStep = () => {
    const useridMissing = !field('userid').value;
    const nameMissing = !field('name').value.trim();
    const urgencyMissing = !root.querySelector('[name="urgencylevel"]:checked');

    root.querySelector('[data-error-for="userid"]').classList.toggle('d-block', useridMissing);
    root.querySelector('[data-error-for="name"]').classList.toggle('d-block', nameMissing);
    root.querySelector('[data-error-for="urgencylevel"]')?.classList.toggle('d-block', urgencyMissing);
    if (useridMissing) {
        root.querySelector('[data-region="user-selector"] .form-autocomplete-input input')?.focus();
    } else if (nameMissing) {
        field('name').focus();
    } else if (urgencyMissing) {
        field('urgencylevel').focus();
    }
    return !useridMissing && !nameMissing && !urgencyMissing;
};

const updateModuleDependency = async(preserve = false) => {
    const module = field('cmid');
    const courseid = config.issite
        ? Number.parseInt(field('modulecourseid').value) || 0
        : config.courseid;
    const competencyid = Number.parseInt(field('competencyid').value) || 0;
    const userid = Number.parseInt(field('userid').value) || 0;
    const request = ++moduleRequest;

    module.setAttribute('data-courseid', courseid);
    module.setAttribute('data-competencyid', competencyid);
    module.setAttribute('data-userid', userid);
    if (!preserve) {
        clearField(module);
    }

    if (!userid || !courseid) {
        setAvailability('cmid', false);
        return;
    }

    try {
        const modules = await Ajax.call([{
            methodname: 'block_fastlearning_search_course_modules',
            args: {contextcourseid: config.courseid, userid, courseid, competencyid, query: ''},
        }])[0];
        if (request === moduleRequest) {
            setAvailability('cmid', modules.length > 0);
        }
    } catch (error) {
        Notification.exception(error);
    }
};

const updateCompetency = (preserve = false) => {
    const competency = field('competencyid');
    if (competency.value && !preserve) {
        const selected = competency.options[competency.selectedIndex];
        if (!field('name').value || field('name').dataset.suggested === 'true') {
            field('name').value = selected.text;
            field('name').dataset.suggested = 'true';
        }
    }
    updateModuleDependency(preserve);
};

const updateCourseAvailability = async(preserve = false) => {
    if (!config.issite) {
        updateModuleDependency(preserve);
        return;
    }
    const userid = Number.parseInt(field('userid').value) || 0;
    const request = ++userRequest;
    const course = field('modulecourseid');
    course.setAttribute('data-userid', userid);
    if (!preserve) {
        clearField(course);
    }

    if (!userid) {
        setAvailability('modulecourseid', false);
        updateModuleDependency(preserve);
        return;
    }

    try {
        const courses = await Ajax.call([{
            methodname: 'block_fastlearning_search_courses',
            args: {userid, query: ''},
        }])[0];
        if (request === userRequest) {
            setAvailability('modulecourseid', courses.length > 0);
            updateModuleDependency(preserve);
        }
    } catch (error) {
        Notification.exception(error);
    }
};

const updateUserAvailability = async() => {
    try {
        const users = await Ajax.call([{
            methodname: 'block_fastlearning_search_users',
            args: {courseid: config.courseid, query: ''},
        }])[0];
        setAvailability('userid', users.length > 0);
        root.querySelector('[data-action="wizard-next"]').disabled = !config.haslevels || users.length === 0;
    } catch (error) {
        Notification.exception(error);
    }
};

const configureEvents = () => {
    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-action="wizard-next"]') && validateFirstStep()) {
            showStep(2);
        } else if (event.target.closest('[data-action="wizard-previous"]')) {
            showStep(1);
        }
    });
    field('competencyid').addEventListener('change', () => updateCompetency());
    field('userid').addEventListener('change', () => updateCourseAvailability());
    field('modulecourseid')?.addEventListener('change', () => updateModuleDependency());
    field('name').addEventListener('input', () => {
        field('name').dataset.suggested = 'false';
    });
    root.addEventListener('submit', (event) => {
        if (event.submitter?.value !== 'savewithcm' || field('cmid').value) {
            return;
        }
        event.preventDefault();
        root.querySelector('[data-error-for="cmid"]').classList.add('d-block');
        root.querySelector('[data-region="cm-selector"] .form-autocomplete-input input')?.focus();
    });
};

export const init = (initialConfig) => {
    root = document.querySelector(ROOT);
    if (!root) {
        return;
    }
    config = initialConfig;
    configureEvents();
    root.querySelector('[data-action="wizard-next"]').disabled = !config.haslevels;
    updateUserAvailability();
    updateCompetency(true);
    updateCourseAvailability(true);
    showStep(config.initialstep);
};
