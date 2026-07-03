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
 * English language strings for block_quizleaderboard.
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['autoupdate']       = 'Auto-update leaderboard';
$string['autoupdateevery']  = 'Every';
$string['autoupdateseconds'] = 'seconds';
$string['backtoquiz']       = 'Back to quiz page';
$string['config_anonymise']        = 'Anonymise student names';
$string['config_anonymise_help']   = 'Replace student names with "Student 1", "Student 2", etc. Useful for displaying on a public screen.';
$string['config_quizid']           = 'Quiz ID';
$string['config_quizid_help']      = 'Enter the numeric ID of the quiz to display. Leave blank to auto-detect from the current quiz page.';
$string['config_showpercentage']   = 'Show percentage column';
$string['config_showstudentid']    = 'Show student ID number';
$string['config_showstudentid_help'] = 'Display the student\'s ID number (idnumber) column in the leaderboard.';
$string['config_title']            = 'Block title';
$string['deferredfeedbacknote'] = 'This quiz uses deferred feedback. Per-question marks will appear once a student submits their attempt and grading is complete. Questions that grade interactively (such as CodeRunner) will show marks as students answer them.';
$string['firstquestionactivity'] = 'First attempt at a question';
$string['fullmarks']         = 'Full marks';
$string['lastquestionactivity']  = 'Last attempt at a question';
$string['lastupdated']       = 'Last updated: {$a}';
$string['leaderboard']       = 'Leaderboard';
$string['minutessincestart'] = '{$a} minutes since first attempt started';
$string['na']                 = 'n/a';
$string['noattempts']        = 'No attempts have been made yet.';
$string['noclosedate']       = 'No close date set';
$string['noopendate']        = 'No open date set';
$string['noquizselected']    = 'No quiz selected. Configure this block to choose a quiz, or place it on a quiz page.';
$string['notadaptive']       = 'This quiz is not in adaptive mode. Per-question marks will only appear after the quiz is submitted and graded.';
$string['notattempted']      = 'Not attempted';
$string['notenoughtimedata'] = 'Not enough attempt history yet to enable time-travel mode.';
$string['notstarted']        = 'Not started';
$string['outof']            = 'Out of {$a}';
$string['partialmarks']      = 'Partial marks';
$string['percentage']        = '%';
$string['pluginname']        = 'Quiz Leaderboard';
$string['privacy:metadata'] = 'The Quiz Leaderboard block only displays existing quiz attempt data stored by the quiz module. It does not store any personal data itself.';
$string['question_short']    = 'Q{$a}';
$string['quizcloses']        = 'Quiz closes';
$string['quizduration']      = 'Duration';
$string['quizleaderboard']   = 'Quiz Leaderboard';
$string['quiznotfound']      = 'The configured quiz could not be found.';
$string['quizopens']         = 'Quiz opens';
$string['rangeend']          = 'Range end';
$string['rangeinvalid']      = 'The range end must be after the range start.';
$string['rangestart']        = 'Range start';
$string['rank']              = '#';
$string['refresh']           = 'Refresh';
$string['resetrange']        = 'Reset to full range';
$string['resetrange_help']   = 'Reset the slider back to the full earliest-to-latest activity range for this quiz.';
$string['showingfirst']     = 'Showing top {$a->shown} of {$a->total} students';
$string['slidergotime']     = 'Jump to:';
$string['slidergoto']       = 'Go';
$string['sortasc']           = 'Sort ascending';
$string['sortdesc']          = 'Sort descending';
$string['student']           = 'Student';
$string['studentid']         = 'ID';
$string['timetravelmode']    = 'Time-travel mode (replay leaderboard history)';
$string['total']             = 'Total';
$string['updaterange']       = 'Update range';
$string['viewfullleaderboard'] = 'View full leaderboard →';
$string['viewingasof']       = 'Viewing leaderboard as of: {$a}';
$string['zeromarks']         = 'Zero marks';
