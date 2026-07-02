<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings for block_quizleaderboard.
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']        = 'Quiz Leaderboard';
$string['quizleaderboard']   = 'Quiz Leaderboard';

// Block config.
$string['config_quizid']           = 'Quiz ID';
$string['config_quizid_help']      = 'Enter the numeric ID of the quiz to display. Leave blank to auto-detect from the current quiz page.';
$string['config_title']            = 'Block title';
$string['config_showstudentid']    = 'Show student ID number';
$string['config_showstudentid_help'] = 'Display the student\'s ID number (idnumber) column in the leaderboard.';
$string['config_showpercentage']   = 'Show percentage column';
$string['config_anonymise']        = 'Anonymise student names';
$string['config_anonymise_help']   = 'Replace student names with "Student 1", "Student 2", etc. Useful for displaying on a public screen.';

// UI strings.
$string['leaderboard']       = 'Leaderboard';
$string['rank']              = '#';
$string['student']           = 'Student';
$string['studentid']         = 'ID';
$string['total']             = 'Total';
$string['percentage']        = '%';
$string['question_short']    = 'Q{$a}';
$string['notstarted']        = 'Not started';
$string['noattempts']        = 'No attempts have been made yet.';
$string['noquizselected']    = 'No quiz selected. Configure this block to choose a quiz, or place it on a quiz page.';
$string['quiznotfound']      = 'The configured quiz could not be found.';
$string['notadaptive']       = 'This quiz is not in adaptive mode. The leaderboard requires adaptive or adaptive (no penalties) question behaviour so that per-question marks can be calculated.';
$string['sortasc']           = 'Sort ascending';
$string['sortdesc']          = 'Sort descending';
$string['lastupdated']       = 'Last updated: {$a}';
$string['refresh']           = 'Refresh';
$string['fullmarks']         = 'Full marks';
$string['partialmarks']      = 'Partial marks';
$string['zeromarks']         = 'Zero marks';
$string['notattempted']      = 'Not attempted';
$string['viewfullleaderboard'] = 'View full leaderboard →';

$string['outof']            = 'Out of {$a}';
$string['showingfirst']     = 'Showing top {$a->shown} of {$a->total} students';
$string['timetravelmode']    = 'Time-travel mode (replay leaderboard history)';
$string['minutessincestart'] = '{$a} minutes since first attempt started';
$string['viewingasof']       = 'Viewing leaderboard as of: {$a}';
$string['notenoughtimedata'] = 'Not enough attempt history yet to enable time-travel mode.';
$string['rangestart']        = 'Range start';
$string['rangeend']          = 'Range end';
$string['updaterange']       = 'Update range';
$string['resetrange']        = 'Reset to full range';
$string['resetrange_help']   = 'Reset the slider back to the full earliest-to-latest activity range for this quiz.';
$string['quizopens']         = 'Quiz opens';
$string['quizcloses']        = 'Quiz closes';
$string['quizduration']      = 'Duration';
$string['firstquestionactivity'] = 'First attempt at a question';
$string['lastquestionactivity']  = 'Last attempt at a question';
$string['noopendate']        = 'No open date set';
$string['noclosedate']       = 'No close date set';
$string['na']                 = 'n/a';
$string['rangeinvalid']      = 'The range end must be after the range start.';
$string['descriptionquestion'] = 'Description (no mark)';
$string['backtoquiz']       = 'Back to quiz page';
$string['autoupdate']       = 'Auto-update leaderboard';
$string['autoupdateevery']  = 'Every';
$string['autoupdateseconds'] = 'seconds';
$string['slidergotime']     = 'Jump to:';
$string['slidergoto']       = 'Go';
// Privacy.
$string['privacy:metadata'] = 'The Quiz Leaderboard block only displays existing quiz attempt data stored by the quiz module. It does not store any personal data itself.';
