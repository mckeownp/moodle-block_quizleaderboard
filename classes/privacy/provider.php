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
 * Privacy API implementation for block_quizleaderboard.
 *
 * This block only reads and displays existing quiz attempt data; it does not
 * create or store any personal data of its own.
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Paul McKeown
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_quizleaderboard\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Privacy provider — this plugin stores no personal data.
 */
class provider implements null_provider {
    /**
     * Return the reason this plugin stores no personal data.
     *
     * @return string The language string key.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
