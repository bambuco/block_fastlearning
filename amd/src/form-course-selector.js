// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Course data source for the resource assignment form autocomplete.
 *
 * @module     block_fastlearning/form-course-selector
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

export const processResults = (selector, courses) => courses.map((course) => ({
    value: course.id,
    label: course.name,
}));

export const transport = (selector, query, success, failure) => {
    const element = document.querySelector(selector);
    Ajax.call([{
        methodname: 'block_fastlearning_search_courses',
        args: {
            userid: Number.parseInt(element.getAttribute('data-userid')) || 0,
            query: query || '',
        },
    }])[0].then(success).catch(failure);
};
