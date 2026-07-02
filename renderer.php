<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Renderer for block_quizleaderboard.
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_quizleaderboard\leaderboard_service;

/** Maximum rows shown in the compact sidebar block. */
define('QL_COMPACT_MAX_ROWS', 20);

/**
 * Output renderer for the Quiz Leaderboard block.
 */
class block_quizleaderboard_renderer extends plugin_renderer_base {

    /**
     * Render the leaderboard.
     *
     * @param stdClass      $quiz       The quiz DB record.
     * @param bool          $canviewall True if the viewer can see all students.
     * @param bool          $compact    True = sidebar summary; false = full-width page.
     * @param int|null      $asoftime   Unix timestamp to replay the leaderboard as of;
     *                                  null = live/current state (default).
     * @param stdClass|null $config     Block instance config (showstudentid, showpercentage,
     *                                  anonymise). Pass null to use sensible defaults — this
     *                                  is required (rather than looked up internally) because
     *                                  $PAGE->blocks is not populated in contexts such as the
     *                                  AJAX endpoint, where introspecting it throws a coding
     *                                  exception ("block_manager has not yet loaded the blocks").
     * @return string HTML output.
     */
    public function render_leaderboard(
        stdClass $quiz,
        bool $canviewall,
        bool $compact = false,
        ?int $asoftime = null,
        ?stdClass $config = null
    ): string {
        global $PAGE;

        $config = $config ?? new stdClass();

        $showstudentid  = !empty($config->showstudentid);
        $showpercentage = isset($config->showpercentage) ? (bool)$config->showpercentage : true;
        $anonymise      = !empty($config->anonymise);

        $service = new leaderboard_service($quiz, $canviewall);
        $data    = $service->get_data($asoftime);

        if (empty($data->rows)) {
            return html_writer::div(
                get_string('noattempts', 'block_quizleaderboard'),
                'alert alert-info ql-no-attempts'
            );
        }

        $PAGE->requires->js_call_amd('block_quizleaderboard/leaderboard', 'init');

        $fullpageurl = new moodle_url('/blocks/quizleaderboard/leaderboard.php', ['quizid' => $quiz->id]);

        $html = '';

        if ($compact) {
            // "View full leaderboard" button at the TOP so it is always accessible.
            $html .= html_writer::div(
                html_writer::link(
                    $fullpageurl,
                    get_string('viewfullleaderboard', 'block_quizleaderboard'),
                    ['class' => 'btn btn-sm btn-outline-primary mb-2 w-100']
                ),
                'ql-fulllink'
            );

            // Compact rank/name/total table, capped at QL_COMPACT_MAX_ROWS.
            $html .= $this->render_compact_table($data, $anonymise, $showpercentage);
        } else {
            // Full per-question table for the standalone page.
            $html .= $this->render_full_table($data, $showpercentage, $anonymise);
            $html .= $this->render_legend();
        }

        if ($asoftime !== null) {
            // Historical replay mode — show the point in time being displayed
            // instead of "last updated now", so it's unambiguous to the teacher.
            $html .= html_writer::div(
                get_string('viewingasof', 'block_quizleaderboard',
                    userdate($asoftime, get_string('strftimedatetimeshort', 'langconfig'))),
                'ql-updated ql-historical text-muted small mt-1'
            );
        } else {
            $html .= html_writer::div(
                get_string('lastupdated', 'block_quizleaderboard',
                    userdate(time(), get_string('strftimedatetimeshort', 'langconfig'))),
                'ql-updated text-muted small mt-1'
            );
        }

        return html_writer::div($html, 'block-quizleaderboard');
    }

    // -------------------------------------------------------------------------
    // Compact sidebar table
    // -------------------------------------------------------------------------

    /**
     * Compact rank / name / total / % table for the sidebar block.
     * Shows at most QL_COMPACT_MAX_ROWS rows.
     *
     * @param stdClass $data
     * @param bool     $anonymise
     * @param bool     $showpercentage
     * @return string
     */
    private function render_compact_table(stdClass $data, bool $anonymise, bool $showpercentage): string {
        $totalmax = $data->total_max;

        $colidx     = 0;
        $headcells  = $this->sortable_th(get_string('rank', 'block_quizleaderboard'), $colidx++, 'ql-col-rank');
        $headcells .= $this->sortable_th(get_string('student', 'block_quizleaderboard'), $colidx++, 'ql-col-name');

        // "Total\nOut of N" header — two lines.
        $totalheader = get_string('total', 'block_quizleaderboard')
            . html_writer::empty_tag('br')
            . html_writer::tag('span',
                get_string('outof', 'block_quizleaderboard', format_float($totalmax, 2, true)),
                ['class' => 'ql-subheader']);
        $headcells .= $this->sortable_th_raw($totalheader, $colidx++, 'ql-col-total');

        if ($showpercentage) {
            $headcells .= $this->sortable_th(get_string('percentage', 'block_quizleaderboard'), $colidx++, 'ql-col-pct');
        }

        $thead = html_writer::tag('thead', html_writer::tag('tr', $headcells));

        $rows       = array_slice($data->rows, 0, QL_COMPACT_MAX_ROWS);
        $tbodyrows  = '';
        $studentnum = 0;
        foreach ($rows as $row) {
            $studentnum++;
            $displayname = $anonymise
                ? get_string('student', 'block_quizleaderboard') . ' ' . $studentnum
                : trim($row->firstname . ' ' . $row->lastname);

            $cells  = html_writer::tag('td', $row->rank,
                          ['data-sort' => $row->rank, 'class' => 'ql-rank']);
            $cells .= html_writer::tag('td', s($displayname),
                          ['data-sort' => strtolower($displayname), 'class' => 'ql-name']);
            $cells .= html_writer::tag('td', format_float($row->total_raw, 2, true),
                          ['data-sort' => $row->total_raw, 'class' => 'ql-total']);
            if ($showpercentage) {
                $cells .= html_writer::tag('td', $row->percentage . '%',
                              ['data-sort' => $row->percentage, 'class' => 'ql-pct']);
            }

            $tbodyrows .= html_writer::tag('tr', $cells);
        }

        // If rows were truncated, show a note.
        $truncnote = '';
        $total = count($data->rows);
        if ($total > QL_COMPACT_MAX_ROWS) {
            $shown = QL_COMPACT_MAX_ROWS;
            $truncnote = html_writer::div(
                get_string('showingfirst', 'block_quizleaderboard', ['shown' => $shown, 'total' => $total]),
                'text-muted small mt-1 ql-truncnote'
            );
        }

        $tbody = html_writer::tag('tbody', $tbodyrows);
        $table = html_writer::tag('table', $thead . $tbody, [
            'class'        => 'table table-sm table-hover ql-table ql-compact',
            'id'           => 'ql-table-' . uniqid(),
            'data-sortcol' => 0,
            'data-sortdir' => 'asc',
        ]);

        return html_writer::div($table, 'ql-table-wrapper') . $truncnote;
    }

    // -------------------------------------------------------------------------
    // Full per-question table (standalone page)
    // -------------------------------------------------------------------------

    /**
     * Full table with per-question colour-coded marks for the standalone page.
     * Always includes student ID and total mark columns.
     *
     * @param stdClass $data
     * @param bool     $showpercentage
     * @param bool     $anonymise
     * @return string
     */
    private function render_full_table(stdClass $data, bool $showpercentage, bool $anonymise): string {
        $slots    = $data->slots;
        $rows     = $data->rows;
        $totalmax = $data->total_max;

        // ---- THEAD ----
        $colidx    = 0;
        $headcells = '';

        $headcells .= $this->sortable_th(get_string('rank',      'block_quizleaderboard'), $colidx++, 'ql-col-rank');
        $headcells .= $this->sortable_th(get_string('student',   'block_quizleaderboard'), $colidx++, 'ql-col-name');
        // Student ID always shown on the full page.
        $headcells .= $this->sortable_th(get_string('studentid', 'block_quizleaderboard'), $colidx++, 'ql-col-id');

        // "Total / Out of N" two-line header.
        $totalheader = get_string('total', 'block_quizleaderboard')
            . html_writer::empty_tag('br')
            . html_writer::tag('span',
                get_string('outof', 'block_quizleaderboard', format_float($totalmax, 2, true)),
                ['class' => 'ql-subheader']);
        $headcells .= $this->sortable_th_raw($totalheader, $colidx++, 'ql-col-total');

        if ($showpercentage) {
            $headcells .= $this->sortable_th(get_string('percentage', 'block_quizleaderboard'), $colidx++, 'ql-col-pct');
        }

        $qnum = 1;
        foreach ($slots as $slot) {
            if ($slot->qtype === 'description') {
                // Description questions are not real scored questions — show a
                // dash in the header with a tooltip, and do NOT increment the
                // question counter so that the next real question keeps the
                // correct sequential number.
                $headcells .= $this->sortable_th(
                    '-',
                    $colidx++,
                    'ql-col-q ql-col-description',
                    get_string('descriptionquestion', 'block_quizleaderboard')
                );
            } else {
                $label   = get_string('question_short', 'block_quizleaderboard', $qnum);
                $tooltip = "Q{$qnum} (" . get_string('outof', 'block_quizleaderboard', format_float($slot->maxmark, 2, true)) . ')';
                $headcells .= $this->sortable_th($label, $colidx++, 'ql-col-q', $tooltip);
                $qnum++;
            }
        }

        $thead = html_writer::tag('thead', html_writer::tag('tr', $headcells));

        // ---- TBODY ----
        $tbodyrows  = '';
        $studentnum = 0;
        foreach ($rows as $row) {
            $studentnum++;
            $displayname = $anonymise
                ? get_string('student', 'block_quizleaderboard') . ' ' . $studentnum
                : trim($row->firstname . ' ' . $row->lastname);

            $cells  = html_writer::tag('td', $row->rank,
                          ['data-sort' => $row->rank, 'class' => 'ql-rank']);
            $cells .= html_writer::tag('td', s($displayname),
                          ['data-sort' => strtolower($displayname), 'class' => 'ql-name']);
            $cells .= html_writer::tag('td', s($row->idnumber),
                          ['data-sort' => strtolower($row->idnumber), 'class' => 'ql-idnumber']);

            // Total: just the raw mark, no "/max" — that's in the header.
            $cells .= html_writer::tag('td', format_float($row->total_raw, 2, true),
                          ['data-sort' => $row->total_raw, 'class' => 'ql-total']);

            if ($showpercentage) {
                $cells .= html_writer::tag('td', $row->percentage . '%',
                              ['data-sort' => $row->percentage, 'class' => 'ql-pct']);
            }

            foreach ($slots as $slot) {
                $s    = $slot->slot;
                $mark = $row->question_marks[$s] ?? null;

                if ($mark === false) {
                    // Description question — informational only, no mark possible.
                    $cells .= html_writer::tag('td', 'n/a', [
                        'data-sort' => -2,
                        'class'     => 'ql-q ql-description',
                        'title'     => get_string('descriptionquestion', 'block_quizleaderboard'),
                    ]);
                } else if ($mark === null) {
                    // Real question, not yet attempted.
                    $cells .= html_writer::tag('td', '-', [
                        'data-sort' => -1,
                        'class'     => 'ql-q ql-notdone',
                        'title'     => get_string('notattempted', 'block_quizleaderboard'),
                    ]);
                } else {
                    $max = (float)$slot->maxmark;

                    if ($mark >= $max - 0.0001) {
                        $cssclass = 'ql-q ql-full';
                        $title    = get_string('fullmarks', 'block_quizleaderboard');
                    } else if ($mark > 0.0001) {
                        $cssclass = 'ql-q ql-partial';
                        $title    = get_string('partialmarks', 'block_quizleaderboard');
                    } else {
                        $cssclass = 'ql-q ql-zero';
                        $title    = get_string('zeromarks', 'block_quizleaderboard');
                    }

                    $cells .= html_writer::tag('td', format_float($mark, 2, true), [
                        'data-sort' => $mark,
                        'class'     => $cssclass,
                        'title'     => $title,
                    ]);
                }
            }

            $tbodyrows .= html_writer::tag('tr', $cells);
        }

        $tbody = html_writer::tag('tbody', $tbodyrows);
        $table = html_writer::tag('table', $thead . $tbody, [
            'class'        => 'table table-sm table-hover ql-table',
            'id'           => 'ql-table-' . uniqid(),
            'data-sortcol' => 0,
            'data-sortdir' => 'asc',
        ]);

        return html_writer::div($table, 'ql-table-wrapper table-responsive');
    }

    /**
     * Render an info panel showing the quiz's configured open time, close
     * time, and duration, alongside the actual observed first and last
     * question activity timestamps (or "n/a" if no question has been
     * attempted at all).
     *
     * This is shown above the time-travel controls so a teacher can spot at a
     * glance whether the default slider range is being stretched by an
     * outlier (e.g. someone who opened the quiz days before everyone else,
     * or a straggler who finished long after the rest of the class) and
     * choose a more useful custom start/end range accordingly.
     *
     * @param stdClass $quiz The quiz DB record (for timeopen/timeclose/timelimit).
     * @param stdClass $data Leaderboard data object (for first/last_question_activity).
     * @return string HTML.
     */
    public function render_quiz_timing_info(stdClass $quiz, stdClass $data): string {
        $format = get_string('strftimedatetimeshort', 'langconfig');

        $opentime  = !empty($quiz->timeopen)  ? userdate($quiz->timeopen, $format)  : get_string('noopendate', 'block_quizleaderboard');
        $closetime = !empty($quiz->timeclose) ? userdate($quiz->timeclose, $format) : get_string('noclosedate', 'block_quizleaderboard');

        if (!empty($quiz->timeopen) && !empty($quiz->timeclose) && $quiz->timeclose > $quiz->timeopen) {
            $duration = format_time($quiz->timeclose - $quiz->timeopen);
        } else {
            $duration = get_string('na', 'block_quizleaderboard');
        }

        $firstactivity = !empty($data->first_question_activity)
            ? userdate($data->first_question_activity, $format)
            : get_string('na', 'block_quizleaderboard');

        $lastactivity = !empty($data->last_question_activity)
            ? userdate($data->last_question_activity, $format)
            : get_string('na', 'block_quizleaderboard');

        $items = [
            ['label' => get_string('quizopens',  'block_quizleaderboard'), 'value' => $opentime],
            ['label' => get_string('quizcloses',  'block_quizleaderboard'), 'value' => $closetime],
            ['label' => get_string('quizduration', 'block_quizleaderboard'), 'value' => $duration],
            ['label' => get_string('firstquestionactivity', 'block_quizleaderboard'), 'value' => $firstactivity],
            ['label' => get_string('lastquestionactivity',  'block_quizleaderboard'), 'value' => $lastactivity],
        ];

        $html = '';
        foreach ($items as $item) {
            $html .= html_writer::div(
                html_writer::span($item['label'] . ': ', 'ql-timing-label') .
                html_writer::span($item['value'], 'ql-timing-value'),
                'ql-timing-item'
            );
        }

        return html_writer::div($html, 'ql-timing-info mb-3');
    }

    /**
     * Render the time-travel toggle + slider controls shown above the full
     * leaderboard table on the standalone page.
     *
     * When toggled on, a range slider lets the teacher pick any point in time
     * between the first recorded activity and the most recent submission;
     * moving it re-renders the table (via AJAX) showing the leaderboard exactly
     * as it stood at that moment. Toggled off (the default), the leaderboard
     * always shows the live/current state using each student's best attempt.
     *
     * A teacher can also narrow (or widen) the slider's own range by entering
     * a custom start and end date/time and clicking "Update range" — useful
     * when an outlier attempt (e.g. someone who started the quiz days early)
     * stretches the default range so much that meaningful variation during
     * the main testing window gets compressed into a tiny portion of the slider.
     *
     * @param stdClass $quiz            The quiz DB record.
     * @param stdClass $data            Leaderboard data object (for earliest/latest_time).
     * @param int      $currentasoftime The asoftime currently in effect (0 = live).
     * @param bool     $hasanytimedata  Whether there is enough timestamp spread to offer the slider.
     * @return string HTML.
     */
    public function render_timetravel_controls(stdClass $quiz, stdClass $data, int $currentasoftime, bool $hasanytimedata): string {
        global $PAGE;

        $enabled = $currentasoftime > 0;

        $toggle = html_writer::div(
            html_writer::tag('label',
                html_writer::empty_tag('input', [
                    'type'    => 'checkbox',
                    'id'      => 'ql-timetravel-toggle',
                    'class'   => 'ql-timetravel-toggle',
                ] + ($enabled ? ['checked' => 'checked'] : [])) .
                ' ' . get_string('timetravelmode', 'block_quizleaderboard'),
                ['class' => 'ql-timetravel-label']
            ),
            'ql-timetravel-toggle-wrapper form-check'
        );

        if (!$hasanytimedata) {
            // Not enough data spread to make a slider meaningful; show the
            // toggle disabled with an explanatory note instead of hiding it
            // entirely, so the UI doesn't appear to be missing a feature.
            $notice = html_writer::div(
                get_string('notenoughtimedata', 'block_quizleaderboard'),
                'text-muted small ql-timetravel-notice'
            );
            return html_writer::div($toggle . $notice, 'ql-timetravel-controls mb-3', ['id' => 'ql-timetravel-controls']);
        }

        $earliest = (int)$data->earliest_time;
        $latest   = (int)$data->latest_time;
        $totalminutes = max(1, (int)ceil(($latest - $earliest) / 60));

        // Slider value: minutes since earliest, derived from currentasoftime if set.
        $currentminutes = $enabled
            ? max(0, min($totalminutes, (int)round(($currentasoftime - $earliest) / 60)))
            : $totalminutes;

        $slider = html_writer::div(
            html_writer::empty_tag('input', [
                'type'  => 'range',
                'id'    => 'ql-timetravel-slider',
                'class' => 'ql-timetravel-slider form-range',
                'min'   => 0,
                'max'   => $totalminutes,
                'step'  => 1,
                'value' => $currentminutes,
                'disabled' => !$enabled,
            ]) .
            html_writer::div(
                get_string('minutessincestart', 'block_quizleaderboard', $currentminutes),
                'ql-timetravel-value',
                [
                    'id' => 'ql-timetravel-value',
                    // Template for JS to interpolate client-side as the slider moves.
                    // JS will replace this with a human-readable duration string.
                    'data-earliest' => $earliest,
                ]
            ) .
            // Manual datetime entry — lets the teacher type or pick a specific
            // point in time to jump directly to, rather than dragging the slider.
            html_writer::div(
                html_writer::tag('label',
                    get_string('slidergotime', 'block_quizleaderboard'),
                    ['for' => 'ql-slider-gotime', 'class' => 'ql-range-label mb-0']
                ) .
                html_writer::empty_tag('input', [
                    'type'     => 'datetime-local',
                    'id'       => 'ql-slider-gotime',
                    'class'    => 'ql-range-input form-control form-control-sm',
                    'value'    => $this->timestamp_to_datetime_local($currentasoftime > 0 ? $currentasoftime : $latest),
                    'disabled' => !$enabled,
                ]) .
                html_writer::tag('button',
                    get_string('slidergoto', 'block_quizleaderboard'),
                    [
                        'type'     => 'button',
                        'id'       => 'ql-slider-goto',
                        'class'    => 'btn btn-sm btn-secondary',
                        'disabled' => !$enabled,
                    ]
                ),
                'ql-slider-gotime-wrapper d-flex align-items-center gap-2 mt-1',
                ['id' => 'ql-slider-gotime-wrapper', 'style' => $enabled ? '' : 'display:none;']
            ),
            'ql-timetravel-slider-wrapper',
            ['id' => 'ql-timetravel-slider-wrapper', 'style' => $enabled ? '' : 'display:none;']
        );

        // Custom range pickers — pre-filled with the current slider bounds so
        // the teacher can see exactly what range is in effect and nudge it
        // rather than having to work out the full datetime from scratch.
        $rangepickers = $this->render_range_pickers($earliest, $latest, $enabled);

        $html = $toggle . $slider . $rangepickers;

        // Pass data needed by JS via data attributes on the wrapper.
        $wrapperattrs = [
            'id'                => 'ql-timetravel-controls',
            'class'             => 'ql-timetravel-controls mb-3',
            'data-quizid'       => $quiz->id,
            'data-earliest'     => $earliest,
            'data-latest'       => $latest,
            'data-totalminutes' => $totalminutes,
            'data-sesskey'      => sesskey(),
            'data-ajaxurl'      => (new moodle_url('/blocks/quizleaderboard/ajax/get_leaderboard.php'))->out(false),
            'data-rangeinvalidmsg' => get_string('rangeinvalid', 'block_quizleaderboard'),
        ];

        $PAGE->requires->js_call_amd('block_quizleaderboard/timetravel', 'init');

        return html_writer::div($html, '', $wrapperattrs);
    }

    /**
     * Render the "custom range" date/time pickers and "Update range" button
     * that let a teacher rebase the slider's min/max to a specific window,
     * rather than always spanning the full earliest-to-latest activity.
     *
     * The inputs use <input type="datetime-local">, which browsers render
     * with a native picker and which JS can read directly as a local
     * date/time string — the AMD module converts that to a unix timestamp
     * client-side (matching the browser's own timezone interpretation) when
     * "Update range" is clicked.
     *
     * @param int  $earliest Current slider lower bound (unix timestamp).
     * @param int  $latest   Current slider upper bound (unix timestamp).
     * @param bool $enabled  Whether time-travel mode is currently switched on.
     * @return string HTML.
     */
    private function render_range_pickers(int $earliest, int $latest, bool $enabled): string {
        $startvalue = $this->timestamp_to_datetime_local($earliest);
        $endvalue   = $this->timestamp_to_datetime_local($latest);

        $startfield = html_writer::div(
            html_writer::tag('label', get_string('rangestart', 'block_quizleaderboard'), [
                'for'   => 'ql-range-start',
                'class' => 'ql-range-label',
            ]) .
            html_writer::empty_tag('input', [
                'type'     => 'datetime-local',
                'id'       => 'ql-range-start',
                'class'    => 'ql-range-input form-control form-control-sm',
                'value'    => $startvalue,
                'disabled' => !$enabled,
            ]),
            'ql-range-field'
        );

        $endfield = html_writer::div(
            html_writer::tag('label', get_string('rangeend', 'block_quizleaderboard'), [
                'for'   => 'ql-range-end',
                'class' => 'ql-range-label',
            ]) .
            html_writer::empty_tag('input', [
                'type'     => 'datetime-local',
                'id'       => 'ql-range-end',
                'class'    => 'ql-range-input form-control form-control-sm',
                'value'    => $endvalue,
                'disabled' => !$enabled,
            ]),
            'ql-range-field'
        );

        $updatebutton = html_writer::tag(
            'button',
            get_string('updaterange', 'block_quizleaderboard'),
            [
                'type'     => 'button',
                'id'       => 'ql-range-update',
                'class'    => 'btn btn-sm btn-secondary',
                'disabled' => !$enabled,
            ]
        );

        $resetbutton = html_writer::tag(
            'button',
            get_string('resetrange', 'block_quizleaderboard'),
            [
                'type'     => 'button',
                'id'       => 'ql-range-reset',
                'class'    => 'btn btn-sm btn-link',
                'disabled' => !$enabled,
                'title'    => get_string('resetrange_help', 'block_quizleaderboard'),
            ]
        );

        $errornote = html_writer::div('', 'ql-range-error text-danger small mt-1', [
            'id'    => 'ql-range-error',
            'style' => 'display:none;',
        ]);

        return html_writer::div(
            $startfield . $endfield . $updatebutton . $resetbutton . $errornote,
            'ql-range-pickers',
            ['id' => 'ql-range-pickers', 'style' => $enabled ? '' : 'display:none;']
        );
    }

    /**
     * Convert a unix timestamp to the value format expected by
     * <input type="datetime-local"> (YYYY-MM-DDTHH:MM), in the user's
     * configured Moodle timezone so what they see matches what they'd expect
     * from the rest of the site.
     *
     * @param int $timestamp
     * @return string
     */
    private function timestamp_to_datetime_local(int $timestamp): string {
        if ($timestamp <= 0) {
            return '';
        }
        // 'Y-m-d\TH:i' matches the datetime-local input format exactly.
        return userdate($timestamp, '%Y-%m-%dT%H:%M', core_date::get_user_timezone(), false);
    }

    /**
     * Render the auto-update controls shown below the time-travel section.
     *
     * Shows an on/off checkbox and an interval entry box (in seconds, default 60).
     * Hidden entirely when time-travel mode is active ($currentasoftime > 0),
     * because auto-updating the live leaderboard while viewing a historical
     * snapshot would be confusing and contradictory.
     *
     * @param int $currentasoftime The asoftime currently in effect (0 = live).
     * @return string HTML.
     */
    public function render_autoupdate_controls(int $currentasoftime): string {
        global $PAGE;

        $hidden = $currentasoftime > 0;

        $checkbox = html_writer::tag('label',
            html_writer::empty_tag('input', [
                'type'    => 'checkbox',
                'id'      => 'ql-autoupdate-toggle',
                'class'   => 'form-check-input',
                'checked' => true,
            ]) . ' ' . get_string('autoupdate', 'block_quizleaderboard'),
            ['class' => 'form-check-label']
        );

        $intervalfield = html_writer::tag('label',
            get_string('autoupdateevery', 'block_quizleaderboard') . ' ' .
            html_writer::empty_tag('input', [
                'type'  => 'number',
                'id'    => 'ql-autoupdate-interval',
                'class' => 'ql-autoupdate-interval form-control form-control-sm d-inline-block',
                'min'   => 5,
                'max'   => 3600,
                'value' => 60,
                'style' => 'width:5rem;',
            ]) . ' ' . get_string('autoupdateseconds', 'block_quizleaderboard'),
            ['class' => 'ms-3 mb-0 d-inline-flex align-items-center gap-1']
        );

        $inner = html_writer::div($checkbox . $intervalfield, 'form-check d-flex align-items-center flex-wrap gap-2');

        $PAGE->requires->js_call_amd('block_quizleaderboard/autoupdate', 'init');

        return html_writer::div($inner, 'ql-autoupdate-controls mb-3' . ($hidden ? ' d-none' : ''), [
            'id'             => 'ql-autoupdate-controls',
            'data-ajaxurl'   => (new moodle_url('/blocks/quizleaderboard/ajax/get_leaderboard.php'))->out(false),
            'data-sesskey'   => sesskey(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    /**
     * Colour-coded legend.
     */
    private function render_legend(): string {
        $items = [
            ['class' => 'ql-full',        'key' => 'fullmarks'],
            ['class' => 'ql-partial',     'key' => 'partialmarks'],
            ['class' => 'ql-zero',        'key' => 'zeromarks'],
            ['class' => 'ql-notdone',     'key' => 'notattempted'],
            ['class' => 'ql-description', 'key' => 'descriptionquestion'],
        ];

        $html = '';
        foreach ($items as $item) {
            $swatch = html_writer::span('', 'ql-swatch ' . $item['class']);
            $label  = html_writer::span(get_string($item['key'], 'block_quizleaderboard'), 'ql-legend-label');
            $html  .= html_writer::span($swatch . $label, 'ql-legend-item');
        }

        return html_writer::div($html, 'ql-legend mt-2');
    }

    /**
     * Sortable <th> with escaped plain-text label.
     */
    private function sortable_th(string $label, int $colidx, string $class = '', ?string $title = null): string {
        return $this->sortable_th_raw(s($label), $colidx, $class, $title);
    }

    /**
     * Sortable <th> accepting pre-built HTML label (e.g. multi-line headers).
     */
    private function sortable_th_raw(string $labelhtml, int $colidx, string $class = '', ?string $title = null): string {
        $attrs = [
            'class'       => trim('ql-th sortable ' . $class),
            'data-colidx' => $colidx,
            'tabindex'    => 0,
            'role'        => 'columnheader',
            'aria-sort'   => 'none',
            'scope'       => 'col',
        ];
        if ($title !== null) {
            $attrs['title'] = $title;
        }

        $icon = html_writer::span('', 'ql-sort-icon', ['aria-hidden' => 'true']);

        return html_writer::tag('th', $labelhtml . $icon, $attrs);
    }

}
