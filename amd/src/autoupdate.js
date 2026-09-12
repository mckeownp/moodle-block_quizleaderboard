// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AMD module: standalone auto-update for the Quiz Leaderboard page.
 *
 * When the full time-travel module (timetravel.js) is also loaded, it takes
 * ownership of the auto-update controls and this module exits immediately so
 * there is no double-handling. This module only acts as the sole driver of
 * auto-update when time-travel is unavailable (e.g. not enough attempt history
 * to offer the slider).
 *
 * @module     block_quizleaderboard/autoupdate
 * @copyright  2024 Paul McKeown
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    'use strict';

    /**
     * Initialise standalone auto-update.
     */
    function init() {
        // If the time-travel controls exist, timetravel.js will manage
        // auto-update. Yield to avoid double-wiring.
        if (document.getElementById('ql-timetravel-controls')) {
            return;
        }

        const autoupdateControls = document.getElementById('ql-autoupdate-controls');
        const autoupdateToggle = document.getElementById('ql-autoupdate-toggle');
        const autoupdateInterval = document.getElementById('ql-autoupdate-interval');
        const container = document.getElementById('ql-leaderboard-container');

        if (!autoupdateToggle || !container) {
            return;
        }

        // Infer quiz id and sesskey from the autoupdate controls or the page URL.
        const url = new URL(window.location.href);
        const quizid = url.searchParams.get('quizid');
        const ajaxurl = autoupdateControls ? autoupdateControls.dataset.ajaxurl : null;
        const sesskey = autoupdateControls ? autoupdateControls.dataset.sesskey : null;

        if (!quizid || !ajaxurl || !sesskey) {
            return;
        }

        let timer = null;

        /**
         * Fetch the latest leaderboard HTML and swap it into the container.
         * @returns {Promise}
         */
        function refreshTable() {
            const params = new URLSearchParams({quizid, sesskey});
            return fetch(ajaxurl + '?' + params.toString(), {
                method: 'GET', credentials: 'same-origin'
            })
            .then(r => {
                if (!r.ok) {
                    throw new Error();
                }
                return r.text();
            })
            .then(html => {
                container.innerHTML = html;
                if (window.require) {
                    window.require(['block_quizleaderboard/leaderboard'], lb => lb.init());
                }
                return;
            })
            .catch(() => { /* Fail silently — don't disrupt the UI. */ });
        }

        /**
         * (Re)start the auto-update timer if the toggle is checked.
         */
        function startTimer() {
            stopTimer();
            if (!autoupdateToggle.checked) {
                return;
            }
            const secs = Math.max(5, parseInt(autoupdateInterval ? autoupdateInterval.value : 60, 10) || 60);
            timer = setInterval(refreshTable, secs * 1000);
        }

        /**
         * Stop the auto-update timer, if running.
         */
        function stopTimer() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        autoupdateToggle.addEventListener('change', () => {
            if (autoupdateToggle.checked) {
                startTimer();
            } else {
                stopTimer();
            }
        });

        if (autoupdateInterval) {
            autoupdateInterval.addEventListener('change', startTimer);
        }

        // Start immediately since auto-update is on by default.
        startTimer();
    }

    return {init};
});
