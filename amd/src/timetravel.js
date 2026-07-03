// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AMD module: time-travel toggle + slider for the Quiz Leaderboard standalone page.
 *
 * Features:
 *  - Toggle time-travel mode on/off
 *  - Drag a range slider to replay the leaderboard at any historical point
 *  - Human-readable duration label (e.g. "3 days, 2 hrs, 5 min" not "4445 minutes")
 *  - Manual datetime entry box to jump directly to a specific time
 *  - Custom start/end range pickers to rebase the slider window
 *  - Auto-update mode (live, not in time-travel): refreshes on a configurable interval
 *  - Auto-update is hidden and paused while time-travel mode is active
 *
 * @module     block_quizleaderboard/timetravel
 * @copyright  2024 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    'use strict';

    // -------------------------------------------------------------------------
    // Duration formatting
    // -------------------------------------------------------------------------

    /**
     * Format a duration in seconds as a human-readable string, scaling the
     * unit automatically: e.g. "5 min", "2 hrs 30 min", "3 days 2 hrs",
     * "2 wks 1 day". Months are approximated as 30 days.
     *
     * @param {number} seconds Total duration in seconds.
     * @return {string}
     */
    function formatDuration(seconds) {
        if (seconds < 0) {
            seconds = 0;
        }

        const mins = Math.floor(seconds / 60);
        const hrs = Math.floor(seconds / 3600);
        const days = Math.floor(seconds / 86400);
        const weeks = Math.floor(seconds / (86400 * 7));
        const months = Math.floor(seconds / (86400 * 30));

        /**
         * Pluralise a unit label if needed, e.g. "2 days" vs "1 day".
         *
         * @param {number} n
         * @param {string} unit
         * @return {string}
         */
        function p(n, unit) {
            return n + ' ' + unit + (n !== 1 ? 's' : '');
        }

        if (months >= 2) {
            const remDays = days - months * 30;
            return p(months, 'month') + (remDays > 0 ? ' ' + p(remDays, 'day') : '');
        }
        if (weeks >= 2) {
            const remDays = days - weeks * 7;
            return p(weeks, 'wk') + (remDays > 0 ? ' ' + p(remDays, 'day') : '');
        }
        if (days >= 2) {
            const remHrs = hrs - days * 24;
            return p(days, 'day') + (remHrs > 0 ? ' ' + p(remHrs, 'hr') : '');
        }
        if (hrs >= 2) {
            const remMins = mins - hrs * 60;
            return p(hrs, 'hr') + (remMins > 0 ? ' ' + p(remMins, 'min') : '');
        }
        return p(mins, 'min');
    }

    // -------------------------------------------------------------------------
    // Datetime-local helpers (browser-local timezone)
    // -------------------------------------------------------------------------

    /**
     * Convert a <input type="datetime-local"> value to a unix timestamp.
     *
     * @param {string} value
     * @return {number|null}
     */
    function datetimeLocalToTimestamp(value) {
        if (!value) {
            return null;
        }
        const ms = Date.parse(value);
        return isNaN(ms) ? null : Math.floor(ms / 1000);
    }

    /**
     * Convert a unix timestamp to the value format expected by
     * <input type="datetime-local">, in the browser's local timezone.
     *
     * @param {number} timestamp
     * @return {string}
     */
    function toDatetimeLocalString(timestamp) {
        const d = new Date(timestamp * 1000);
        const pad = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
            'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    // -------------------------------------------------------------------------
    // Main init
    // -------------------------------------------------------------------------

    /**
     * Initialise the time-travel controls on the standalone leaderboard page.
     */
    function init() {
        const controls = document.getElementById('ql-timetravel-controls');
        if (!controls) {
            return;
        }

        const toggle = document.getElementById('ql-timetravel-toggle');
        const sliderWrap = document.getElementById('ql-timetravel-slider-wrapper');
        const slider = document.getElementById('ql-timetravel-slider');
        const valueLabel = document.getElementById('ql-timetravel-value');
        const container = document.getElementById('ql-leaderboard-container');
        const rangePickers = document.getElementById('ql-range-pickers');
        const rangeStartInput = document.getElementById('ql-range-start');
        const rangeEndInput = document.getElementById('ql-range-end');
        const rangeUpdateBtn = document.getElementById('ql-range-update');
        const rangeResetBtn = document.getElementById('ql-range-reset');
        const rangeError = document.getElementById('ql-range-error');
        const goTimeInput = document.getElementById('ql-slider-gotime');
        const goTimeBtn = document.getElementById('ql-slider-goto');
        const goTimeWrapper = document.getElementById('ql-slider-gotime-wrapper');

        // Auto-update elements (may not exist if time-travel mode is active on load).
        const autoupdateControls = document.getElementById('ql-autoupdate-controls');
        const autoupdateToggle = document.getElementById('ql-autoupdate-toggle');
        const autoupdateInterval = document.getElementById('ql-autoupdate-interval');

        if (!toggle || !slider || !container) {
            // Notice-only state (not enough time data).
            return;
        }

        const quizid = parseInt(controls.dataset.quizid, 10);
        const ajaxurl = controls.dataset.ajaxurl;
        const sesskey = controls.dataset.sesskey;

        const originalEarliest = parseInt(controls.dataset.earliest, 10);
        const originalLatest = parseInt(controls.dataset.latest, 10);

        let earliest = originalEarliest;
        let latest = originalLatest;
        let totalMinutes = parseInt(controls.dataset.totalminutes, 10);

        let debounceTimer = null;
        let autoupdateTimer = null;

        // ---------------------------------------------------------------
        // Duration label (human-readable)
        // ---------------------------------------------------------------

        /**
         * Update the human-readable duration label and keep the "go to time"
         * input in sync with the current slider position.
         */
        function updateLabel() {
            const minutes = parseInt(slider.value, 10);
            const seconds = minutes * 60;
            const elapsed = formatDuration(seconds);
            // Show both elapsed duration and the actual timestamp.
            const absTime = new Date((earliest + seconds) * 1000);
            const pad = n => String(n).padStart(2, '0');
            const timeStr = absTime.getFullYear() + '-' +
                             pad(absTime.getMonth() + 1) + '-' +
                             pad(absTime.getDate()) + ' ' +
                             pad(absTime.getHours()) + ':' +
                             pad(absTime.getMinutes());

            if (valueLabel) {
                valueLabel.textContent = elapsed + ' since start (' + timeStr + ')';
            }

            // Keep the "go to time" input in sync with the slider position.
            if (goTimeInput && !goTimeInput.matches(':focus')) {
                goTimeInput.value = toDatetimeLocalString(earliest + seconds);
            }
        }

        // ---------------------------------------------------------------
        // Table refresh
        // ---------------------------------------------------------------

        /**
         * Fetch the leaderboard HTML "as of" a given point in time (or live,
         * if asoftime is 0) and swap it into the container.
         *
         * @param {number} asoftime Unix timestamp cut-off, or 0 for live data.
         */
        function refreshTable(asoftime) {
            const params = new URLSearchParams({quizid, sesskey});
            if (asoftime > 0) {
                params.set('asoftime', asoftime);
            }

            fetch(ajaxurl + '?' + params.toString(), {
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
            .catch(() => {
                const notice = document.createElement('div');
                notice.className = 'alert alert-warning';
                notice.textContent = 'Unable to update the leaderboard. Please try again.';
                container.prepend(notice);
            });

            const url = new URL(window.location.href);
            if (asoftime > 0) {
                url.searchParams.set('asoftime', asoftime);
            } else {
                url.searchParams.delete('asoftime');
            }
            window.history.replaceState({}, '', url.toString());
        }

        /**
         * Compute the unix timestamp currently represented by the slider position.
         *
         * @return {number}
         */
        function currentAsOfTime() {
            return earliest + parseInt(slider.value, 10) * 60;
        }

        /**
         * Debounce table refreshes while the slider is being dragged.
         */
        function debouncedRefresh() {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = setTimeout(() => refreshTable(currentAsOfTime()), 250);
        }

        // ---------------------------------------------------------------
        // Auto-update (live mode only)
        // ---------------------------------------------------------------

        /**
         * (Re)start the auto-update timer, if enabled.
         */
        function startAutoupdate() {
            stopAutoupdate();
            if (!autoupdateToggle || !autoupdateToggle.checked) {
                return;
            }
            const secs = Math.max(5, parseInt(autoupdateInterval ? autoupdateInterval.value : 60, 10) || 60);
            autoupdateTimer = setInterval(() => refreshTable(0), secs * 1000);
        }

        /**
         * Stop the auto-update timer, if running.
         */
        function stopAutoupdate() {
            if (autoupdateTimer) {
                clearInterval(autoupdateTimer);
                autoupdateTimer = null;
            }
        }

        /**
         * Show/hide the auto-update controls and pause/resume the timer to
         * match whether time-travel mode is active.
         *
         * @param {boolean} enabled
         */
        function setTimeTravelActive(enabled) {
            // Hide/show auto-update controls and pause/resume accordingly.
            if (autoupdateControls) {
                autoupdateControls.classList.toggle('d-none', enabled);
            }
            if (enabled) {
                stopAutoupdate();
            } else {
                startAutoupdate();
            }
        }

        // ---------------------------------------------------------------
        // Slider rebase
        // ---------------------------------------------------------------

        /**
         * Rebase the slider's min/max to a new earliest/latest window,
         * preserving the current "as of" position as closely as possible.
         *
         * @param {number} newEarliest
         * @param {number} newLatest
         */
        function rebaseSlider(newEarliest, newLatest) {
            const previousAsOf = currentAsOfTime();

            earliest = newEarliest;
            latest = newLatest;
            totalMinutes = Math.max(1, Math.ceil((latest - earliest) / 60));

            slider.min = 0;
            slider.max = totalMinutes;

            const clampedAsOf = Math.min(Math.max(previousAsOf, earliest), latest);
            slider.value = Math.round((clampedAsOf - earliest) / 60);

            updateLabel();
            refreshTable(currentAsOfTime());
        }

        // ---------------------------------------------------------------
        // Range error helper
        // ---------------------------------------------------------------

        /**
         * Show or clear the range-validation error message.
         *
         * @param {string} msg Error message, or an empty string to clear it.
         */
        function setRangeError(msg) {
            if (!rangeError) {
                return;
            }
            rangeError.textContent = msg;
            rangeError.style.display = msg ? '' : 'none';
        }

        // ---------------------------------------------------------------
        // Wire up all controls
        // ---------------------------------------------------------------

        // Time-travel toggle.
        toggle.addEventListener('change', () => {
            const enabled = toggle.checked;
            slider.disabled = !enabled;
            if (sliderWrap) {
                sliderWrap.style.display = enabled ? '' : 'none';
            }
            if (goTimeWrapper) {
                goTimeWrapper.style.display = enabled ? '' : 'none';
            }
            if (rangePickers) {
                rangePickers.style.display = enabled ? '' : 'none';
            }

            [rangeStartInput, rangeEndInput, rangeUpdateBtn, rangeResetBtn, goTimeInput, goTimeBtn]
                .forEach(el => {
                    if (el) {
                        el.disabled = !enabled;
                    }
                });

            if (enabled) {
                setTimeTravelActive(true);
                refreshTable(currentAsOfTime());
            } else {
                setRangeError('');
                setTimeTravelActive(false);
                refreshTable(0);
            }
        });

        // Slider drag.
        slider.addEventListener('input', () => {
            updateLabel();
            debouncedRefresh();
        });

        // "Go to time" button — jump the slider to the entered datetime.
        if (goTimeBtn) {
            goTimeBtn.addEventListener('click', () => {
                const ts = datetimeLocalToTimestamp(goTimeInput ? goTimeInput.value : null);
                if (ts === null) {
                    return;
                }
                const clamped = Math.min(Math.max(ts, earliest), latest);
                slider.value = Math.round((clamped - earliest) / 60);
                updateLabel();
                debouncedRefresh();
            });
        }

        // Update slider when go-time input changes (Enter key or blur).
        if (goTimeInput) {
            goTimeInput.addEventListener('change', () => {
                const ts = datetimeLocalToTimestamp(goTimeInput.value);
                if (ts === null) {
                    return;
                }
                const clamped = Math.min(Math.max(ts, earliest), latest);
                slider.value = Math.round((clamped - earliest) / 60);
                updateLabel();
                debouncedRefresh();
            });
        }

        // Update range button.
        if (rangeUpdateBtn) {
            rangeUpdateBtn.addEventListener('click', () => {
                const newStart = datetimeLocalToTimestamp(rangeStartInput ? rangeStartInput.value : null);
                const newEnd = datetimeLocalToTimestamp(rangeEndInput ? rangeEndInput.value : null);
                if (newStart === null || newEnd === null || newEnd <= newStart) {
                    setRangeError(controls.dataset.rangeinvalidmsg || 'Invalid date/time range.');
                    return;
                }
                setRangeError('');
                rebaseSlider(newStart, newEnd);
            });
        }

        // Reset range button.
        if (rangeResetBtn) {
            rangeResetBtn.addEventListener('click', () => {
                setRangeError('');
                if (rangeStartInput) {
                    rangeStartInput.value = toDatetimeLocalString(originalEarliest);
                }
                if (rangeEndInput) {
                    rangeEndInput.value = toDatetimeLocalString(originalLatest);
                }
                rebaseSlider(originalEarliest, originalLatest);
            });
        }

        // Auto-update toggle.
        if (autoupdateToggle) {
            autoupdateToggle.addEventListener('change', () => {
                if (autoupdateToggle.checked) {
                    startAutoupdate();
                } else {
                    stopAutoupdate();
                }
            });
        }

        // Auto-update interval change — restart the timer with the new value.
        if (autoupdateInterval) {
            autoupdateInterval.addEventListener('change', () => {
                startAutoupdate();
            });
        }

        // ---------------------------------------------------------------
        // Initial state
        // ---------------------------------------------------------------

        updateLabel();

        // If time-travel is NOT already active on page load, start auto-update.
        if (!toggle.checked) {
            setTimeTravelActive(false); // Starts auto-update in live mode.
        } else {
            setTimeTravelActive(true); // Suppress auto-update in time-travel.
        }
    }

    return {init};
});
