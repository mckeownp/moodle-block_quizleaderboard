<?php
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
 * AJAX endpoint: returns the leaderboard table HTML rendered "as of" a given
 * timestamp, used by the time-travel slider on the standalone leaderboard page
 * to update the table live without a full page reload.
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Paul McKeown
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/blocks/quizleaderboard/renderer.php');

use block_quizleaderboard\leaderboard_service;

$quizid   = required_param('quizid', PARAM_INT);
$asoftime = optional_param('asoftime', 0, PARAM_INT);

$quiz   = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
$cm     = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('block/quizleaderboard:viewall', $context);

require_sesskey();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/quizleaderboard/ajax/get_leaderboard.php'));

header('Content-Type: text/html; charset=utf-8');

// NOTE: we deliberately do NOT call $PAGE->get_renderer()'s normal config
// lookup via $PAGE->blocks here — this script never runs through Moodle's
// page output flow (no header()/footer()), so $PAGE->blocks has not been
// loaded and querying it throws "block_manager has not yet loaded the
// blocks". Instead we look the block's display config up directly from the
// database, keyed by quizid.
$blockconfig = leaderboard_service::get_block_config_for_quiz($quizid);

$renderer = $PAGE->get_renderer('block_quizleaderboard');
echo $renderer->render_leaderboard($quiz, true, false, $asoftime > 0 ? $asoftime : null, $blockconfig);
