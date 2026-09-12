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
 * Capability definitions for block_quizleaderboard.
 *
 * Only teachers, non-editing teachers, and managers can add or see this block.
 * Students have no access at all — neither to add the block nor to view its
 * content. The block's get_content() also enforces this at render time via
 * mod/quiz:viewreports, so even if a student somehow encountered the block
 * on a page, they would see nothing.
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Paul McKeown
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Ability to add the block to the My Dashboard page.
    // Restricted to teachers and above — students must not be able to add it.
    'block/quizleaderboard:myaddinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Ability to add the block to a course or activity page.
    // Non-editing teachers can add it (they need to monitor students) but
    // students cannot.
    'block/quizleaderboard:addinstance' => [
        'riskbitmask'  => RISK_SPAM | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Ability to view the leaderboard content (all students' data).
    // Non-editing teachers are explicitly included so they can monitor exams
    // without needing editing rights on the course.
    'block/quizleaderboard:viewall' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
