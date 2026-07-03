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
 * Quiz Leaderboard block main class.
 *
 * Displays a live leaderboard for students attempting an open quiz.
 * Works with any quiz behaviour — adaptive mode shows marks question-by-question
 * as students answer; deferred-feedback mode shows marks once the quiz is
 * submitted and graded (or immediately for questions like CodeRunner that
 * grade interactively regardless of the quiz-level behaviour setting).
 * Shows per-question marks colour-coded green/orange/red/dash, plus running totals.
 * The table is fully sortable client-side.
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Block class definition.
 */
class block_quizleaderboard extends block_base {

    /**
     * Initialise the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_quizleaderboard');
    }

    /**
     * Allow multiple instances of this block on a page.
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * This block has a settings form.
     */
    public function has_config() {
        return false;
    }

    /**
     * Allow the block to have a configuration form per instance.
     */
    public function instance_allow_config() {
        return true;
    }

    /**
     * Applicable formats — show everywhere but make it most useful on quiz/course pages.
     */
    public function applicable_formats() {
        return [
            'all'           => false,
            'site'          => true,
            'course'        => true,
            'course-view'   => true,
            'mod'           => true,
            'mod-quiz'      => true,
        ];
    }

    /**
     * Return the block content.
     *
     * @return stdClass Block content object.
     */
    public function get_content() {
        global $DB, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        // Determine which quiz to show.
        $quizid = !empty($this->config->quizid) ? (int)$this->config->quizid : 0;

        // Auto-detect quiz from page context if not explicitly configured.
        if (!$quizid && $this->page->cm && $this->page->cm->modname === 'quiz') {
            $quizid = $this->page->cm->instance;
        }

        if (!$quizid) {
            $this->content->text = html_writer::div(
                get_string('noquizselected', 'block_quizleaderboard'),
                'alert alert-info'
            );
            return $this->content;
        }

        // Load the quiz record and verify it exists.
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', IGNORE_MISSING);
        if (!$quiz) {
            $this->content->text = html_writer::div(
                get_string('quiznotfound', 'block_quizleaderboard'),
                'alert alert-warning'
            );
            return $this->content;
        }

        // Only teachers, non-editing teachers, and managers may view the
        // leaderboard. Students get an empty content object — the block
        // renders as blank rather than showing any error or content.
        // We use the plugin's own viewall capability (defined in db/access.php)
        // rather than mod/quiz:viewreports so access control is self-contained
        // and doesn't accidentally change if quiz report permissions are altered.
        $context = context_course::instance($quiz->course);
        if (!has_capability('block/quizleaderboard:viewall', $context)) {
            return $this->content;
        }

        // Build the leaderboard HTML (compact sidebar view).
        $renderer = $this->page->get_renderer('block_quizleaderboard');
        $this->content->text = $renderer->render_leaderboard($quiz, true, true, null, $this->config ?? null);

        return $this->content;
    }
}
