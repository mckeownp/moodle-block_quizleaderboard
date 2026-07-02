<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Standalone full-width leaderboard page.
 *
 * Supports an optional "time travel" mode: a teacher can drag a slider to see
 * the leaderboard exactly as it stood at any point between the first and last
 * graded question activity for this quiz, by passing asoftime (a unix
 * timestamp) as a query parameter. When asoftime is omitted, the live/current
 * leaderboard (using each student's best attempt) is shown. The default
 * slider range deliberately excludes attempt start times (a student can open
 * an untimed quiz long before answering anything), but a teacher can widen it
 * to any custom window via the start/end date-time pickers.
 *
 * URL: /blocks/quizleaderboard/leaderboard.php?quizid=X[&asoftime=Y]
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/blocks/quizleaderboard/renderer.php');

use block_quizleaderboard\leaderboard_service;

$quizid   = required_param('quizid', PARAM_INT);
$asoftime = optional_param('asoftime', 0, PARAM_INT); // 0 = live/current mode.

// Load quiz and course.
$quiz   = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
$cm     = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

// Auth + context.
require_login($course, false, $cm);
$context = context_module::instance($cm->id);

// Page setup.
$urlparams = ['quizid' => $quizid];
if ($asoftime > 0) {
    $urlparams['asoftime'] = $asoftime;
}
$url = new moodle_url('/blocks/quizleaderboard/leaderboard.php', $urlparams);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$PAGE->set_title(get_string('leaderboard', 'block_quizleaderboard') . ': ' . format_string($quiz->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

// Restrict to teachers, non-editing teachers, and managers only.
if (!has_capability('block/quizleaderboard:viewall', $context)) {
    throw new \moodle_exception('nopermissions', 'error', '', get_string('leaderboard', 'block_quizleaderboard'));
}

// Adaptive mode check.
$adaptivemodes = ['adaptive', 'adaptivenopenalty'];
if (!in_array($quiz->preferredbehaviour, $adaptivemodes)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notadaptive', 'block_quizleaderboard'), 'warning');
    echo $OUTPUT->footer();
    die;
}

// Render.
$renderer = $PAGE->get_renderer('block_quizleaderboard');

echo $OUTPUT->header();

echo html_writer::start_div('block-quizleaderboard-page container-fluid');

// Top navigation row: back link on the left, heading centred.
$quizviewurl = new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]);
echo html_writer::div(
    html_writer::link(
        $quizviewurl,
        '← ' . get_string('backtoquiz', 'block_quizleaderboard'),
        ['class' => 'btn btn-sm btn-outline-secondary ql-back-link']
    ),
    'ql-top-nav mb-2'
);

echo html_writer::tag(
    'h2',
    get_string('leaderboard', 'block_quizleaderboard') . ': ' . format_string($quiz->name),
    ['class' => 'mb-3']
);

// Time-travel controls (toggle + slider). The slider's range is computed from
// the actual earliest/latest step timestamps for this quiz so it always covers
// exactly the period during which activity occurred.
$service     = new leaderboard_service($quiz, true);
$probe       = $service->get_data(); // live data, also gives us earliest/latest bounds.
$hasanytimedata = !empty($probe->earliest_time) && !empty($probe->latest_time)
    && $probe->latest_time > $probe->earliest_time;

// Quiz timing info panel: open/close/duration plus first/last actual question
// activity, so a teacher can see at a glance whether the slider's default
// range is being skewed by an outlier (e.g. a student who started very early
// or very late) and pick a more sensible custom range using the date/time
// pickers below.
echo $renderer->render_quiz_timing_info($quiz, $probe);

echo $renderer->render_timetravel_controls($quiz, $probe, $asoftime, $hasanytimedata);

// Auto-update controls (visible in live mode only, hidden when time-travel is on).
echo $renderer->render_autoupdate_controls($asoftime);

// The table itself lives in a container the JS module refreshes via AJAX.
echo html_writer::start_div('', ['id' => 'ql-leaderboard-container']);
$blockconfig = leaderboard_service::get_block_config_for_quiz($quizid);
echo $renderer->render_leaderboard($quiz, true, false, $asoftime > 0 ? $asoftime : null, $blockconfig);
echo html_writer::end_div();

echo html_writer::end_div();

echo $OUTPUT->footer();
