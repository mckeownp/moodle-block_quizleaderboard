// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

/**
 * AMD module: client-side sortable table for the Quiz Leaderboard block.
 *
 * Clicking (or pressing Enter/Space on) any column header toggles that column
 * between ascending and descending sort. Rows carry data-sort attributes with
 * numeric or string values used for comparison.
 *
 * @module     block_quizleaderboard/leaderboard
 * @copyright  2024 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    'use strict';

    /**
     * Initialise all leaderboard tables on the page.
     */
    function init() {
        document.querySelectorAll('.ql-table').forEach(initTable);
    }

    /**
     * Attach sort behaviour to a single table.
     *
     * @param {HTMLTableElement} table
     */
    function initTable(table) {
        const headers = table.querySelectorAll('th.sortable');

        // Current sort state per table.
        let sortState = {
            col: -1,
            dir: 'none', // 'asc' | 'desc' | 'none'
        };

        headers.forEach(function(th) {
            th.addEventListener('click', function() {
                handleSort(table, th, headers, sortState);
            });

            // Keyboard accessibility.
            th.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleSort(table, th, headers, sortState);
                }
            });
        });
    }

    /**
     * Sort the table rows on the clicked column.
     *
     * @param {HTMLTableElement} table
     * @param {HTMLElement}      th        The clicked header cell.
     * @param {NodeList}         headers   All sortable headers.
     * @param {Object}           sortState Mutable sort-state object.
     */
    function handleSort(table, th, headers, sortState) {
        const colidx = parseInt(th.dataset.colidx, 10);

        // Toggle direction.
        if (sortState.col === colidx) {
            sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
        } else {
            sortState.col = colidx;
            sortState.dir = 'asc';
        }

        // Update aria-sort attributes.
        headers.forEach(function(h) {
            const idx = parseInt(h.dataset.colidx, 10);
            if (idx === colidx) {
                h.setAttribute('aria-sort', sortState.dir === 'asc' ? 'ascending' : 'descending');
                h.classList.add('ql-sorted');
                h.classList.toggle('ql-sort-asc', sortState.dir === 'asc');
                h.classList.toggle('ql-sort-desc', sortState.dir === 'desc');
            } else {
                h.setAttribute('aria-sort', 'none');
                h.classList.remove('ql-sorted', 'ql-sort-asc', 'ql-sort-desc');
            }
        });

        // Sort the rows.
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort(function(a, b) {
            const cellA = a.querySelectorAll('td')[colidx];
            const cellB = b.querySelectorAll('td')[colidx];

            if (!cellA || !cellB) {
                return 0;
            }

            const rawA = cellA.dataset.sort;
            const rawB = cellB.dataset.sort;

            // Try numeric comparison first.
            const numA = parseFloat(rawA);
            const numB = parseFloat(rawB);

            let cmp;
            if (!isNaN(numA) && !isNaN(numB)) {
                cmp = numA - numB;
            } else {
                // String comparison (locale-aware).
                cmp = String(rawA).localeCompare(String(rawB));
            }

            return sortState.dir === 'asc' ? cmp : -cmp;
        });

        // Re-append rows in sorted order.
        rows.forEach(function(row) {
            tbody.appendChild(row);
        });
    }

    return {
        init: init,
    };
});
