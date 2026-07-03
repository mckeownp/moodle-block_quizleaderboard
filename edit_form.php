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
 * Block instance configuration form.
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Form for configuring a block_quizleaderboard instance.
 */
class block_quizleaderboard_edit_form extends block_edit_form {
    /**
     * Add instance-specific settings to the form.
     *
     * @param MoodleQuickForm $mform The form being built.
     */
    protected function specific_definition($mform) {

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Block title override.
        $mform->addElement(
            'text',
            'config_title',
            get_string('config_title', 'block_quizleaderboard')
        );
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', '');

        // Quiz ID.
        $mform->addElement(
            'text',
            'config_quizid',
            get_string('config_quizid', 'block_quizleaderboard')
        );
        $mform->addHelpButton('config_quizid', 'config_quizid', 'block_quizleaderboard');
        $mform->setType('config_quizid', PARAM_INT);
        $mform->setDefault('config_quizid', 0);

        // Show student ID number column.
        $mform->addElement(
            'advcheckbox',
            'config_showstudentid',
            get_string('config_showstudentid', 'block_quizleaderboard')
        );
        $mform->addHelpButton('config_showstudentid', 'config_showstudentid', 'block_quizleaderboard');
        $mform->setDefault('config_showstudentid', 0);

        // Show percentage column.
        $mform->addElement(
            'advcheckbox',
            'config_showpercentage',
            get_string('config_showpercentage', 'block_quizleaderboard')
        );
        $mform->setDefault('config_showpercentage', 1);

        // Anonymise student names.
        $mform->addElement(
            'advcheckbox',
            'config_anonymise',
            get_string('config_anonymise', 'block_quizleaderboard')
        );
        $mform->addHelpButton('config_anonymise', 'config_anonymise', 'block_quizleaderboard');
        $mform->setDefault('config_anonymise', 0);
    }
}
