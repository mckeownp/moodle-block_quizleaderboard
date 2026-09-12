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
 * Version information for block_quizleaderboard.
 *
 * Requires Moodle 5.0+ (version 2024100700). This simplifies the codebase
 * because we can rely on the modern question bank schema
 * (question_references / question_versions tables always present) and
 * mod_quiz\quiz_settings / mod_quiz\quiz_attempt namespaced classes.
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Paul McKeown
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026070300;       // YYYYMMDDXX.
$plugin->requires  = 2024100700;       // Moodle 5.0.0.
$plugin->component = 'block_quizleaderboard';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '2.3.0';
$plugin->supported = [500, 503];       // Moodle 5.0 – 5.3.
