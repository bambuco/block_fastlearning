// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Course-module data source for the resource assignment form autocomplete.
 *
 * @module     block_fastlearning/form-course-module-selector
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

export const processResults = (selector, modules) => modules.map((module) => ({
    value: module.id,
    label: module.name,
}));

export const transport = (selector, query, success, failure) => {
    const element = document.querySelector(selector);
    Ajax.call([{
        methodname: 'block_fastlearning_search_course_modules',
        args: {
            contextcourseid: Number.parseInt(element.getAttribute('data-contextcourseid')),
            userid: Number.parseInt(element.getAttribute('data-userid')) || 0,
            courseid: Number.parseInt(element.getAttribute('data-courseid')) || 0,
            competencyid: Number.parseInt(element.getAttribute('data-competencyid')) || 0,
            query: query || '',
        },
    }])[0].then(success).catch(failure);
};
