<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Behat custom step definitions for block_quizleaderboard.
 *
 * These steps wrap Moodle's quiz attempt / question engine APIs so that test
 * scenarios can set up adaptive-mode quiz attempts with specific per-question
 * outcomes (correct / incorrect / not attempted) and specific timestamps,
 * without having to click through the actual quiz attempt UI for every case.
 * This keeps the suite fast and deterministic while still exercising the real
 * data layer that the leaderboard reads from.
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
// mod_quiz's attempt-related classes live under the mod_quiz namespace on
// modern Moodle (quiz_settings used to be called plain "quiz" in much older
// versions, before the mod_quiz\ namespace migration). We import them here
// so the rest of this file can refer to them as the short, unqualified
// names quiz_settings / quiz_attempt — using those names completely
// unqualified inside a global-namespace class (as this one is) would
// otherwise resolve to a non-existent global \quiz_settings, which is
// exactly what produced "Class quiz_settings not found".
use mod_quiz\quiz_settings;
use mod_quiz\quiz_attempt;

/**
 * Behat context for block_quizleaderboard step definitions.
 */
class behat_block_quizleaderboard extends behat_base {

    /**
     * In-memory map of quizname => quiz id, populated as scenarios reference
     * quizzes by name, to avoid repeated DB lookups within one scenario.
     *
     * @var array
     */
    protected $quizidcache = [];

    /**
     * In-memory map of "username|quizname" => quiz attempt id, so that
     * subsequent "question X is answered..." steps know which attempt to use.
     *
     * @var array
     */
    protected $attemptidcache = [];

    // -------------------------------------------------------------------
    // Setup: adding the block to a quiz page
    // -------------------------------------------------------------------

    /**
     * Add a block_quizleaderboard instance configured for a specific quiz to
     * the quiz's course page (so it is visible on the quiz "View" page via
     * 'all'/'mod-quiz' applicable format inheritance).
     *
     * @Given /^the block_quizleaderboard plugin is added with quizid for "(?P<quiz_name>(?:[^"]|\\")*)" in course "(?P<course_shortname>(?:[^"]|\\")*)"$/
     *
     * @param string $quizname
     * @param string $courseshortname
     */
    public function the_block_is_added_for_quiz(string $quizname, string $courseshortname) {
        global $DB;

        $quizid = $this->get_quiz_id($quizname);
        $course = $DB->get_record('course', ['shortname' => $courseshortname], '*', MUST_EXIST);

        $context = context_course::instance($course->id);

        $blockinstance = new stdClass();
        $blockinstance->blockname     = 'quizleaderboard';
        $blockinstance->parentcontextid = $context->id;
        $blockinstance->showinsubcontexts = 1;
        $blockinstance->pagetypepattern = 'mod-quiz-*';
        $blockinstance->subpagepattern  = null;
        $blockinstance->defaultregion   = 'side-pre';
        $blockinstance->defaultweight   = 0;
        $blockinstance->configdata = base64_encode(serialize((object)[
            'quizid'          => $quizid,
            'showstudentid'   => 1,
            'showpercentage'  => 1,
            'anonymise'       => 0,
        ]));
        $blockinstance->timecreated  = time();
        $blockinstance->timemodified = time();

        $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);

        // Create the block context so capability checks resolve correctly.
        context_block::instance($blockinstance->id);
    }

    // -------------------------------------------------------------------
    // Setup: starting quiz attempts
    // -------------------------------------------------------------------

    /**
     * Start a fresh in-progress quiz attempt for a user, with no responses yet.
     *
     * Named "has begun a leaderboard attempt at" (rather than the more natural
     * "has started an attempt at") because Moodle core's own behat_mod_quiz
     * context already defines a step matching
     * `user "X" has started an attempt at quiz "Y"` — using the same phrasing
     * here causes Behat to report "Ambiguous match" and refuse to run the
     * scenario, since it can't tell which of the two matching definitions to use.
     *
     * @Given /^user "(?P<username>(?:[^"]|\\")*)" has begun a leaderboard attempt at quiz "(?P<quiz_name>(?:[^"]|\\")*)"$/
     *
     * @param string $username
     * @param string $quizname
     */
    public function user_has_started_an_attempt(string $username, string $quizname) {
        $this->start_attempt($username, $quizname, null);
    }

    /**
     * Start a quiz attempt for a user and immediately submit the given
     * responses (without grading them — grading is done via the separate
     * "question X is answered correctly/incorrectly" steps so that tests can
     * control timing and correctness independently).
     *
     * @Given /^user "(?P<username>(?:[^"]|\\")*)" has begun a leaderboard attempt at quiz "(?P<quiz_name>(?:[^"]|\\")*)" with responses:$/
     *
     * @param string    $username
     * @param string    $quizname
     * @param TableNode $table Columns: slot, response.
     */
    public function user_has_started_an_attempt_with_responses(string $username, string $quizname, TableNode $table) {
        // Responses table is informational/documents intent for the reader;
        // actual grading happens via the explicit "answered correctly/incorrectly"
        // steps below, which is what actually drives mark calculation.
        $this->start_attempt($username, $quizname, null);
    }

    /**
     * Start a quiz attempt at a specific relative time, e.g. "-60 minutes".
     * Used by time-travel scenarios to control the attempt's timestart.
     *
     * @Given /^user "(?P<username>(?:[^"]|\\")*)" started quiz "(?P<quiz_name>(?:[^"]|\\")*)" at "(?P<when>(?:[^"]|\\")*)"$/
     *
     * @param string $username
     * @param string $quizname
     * @param string $when A strtotime()-compatible relative time string, e.g. "-60 minutes".
     */
    public function user_started_quiz_at(string $username, string $quizname, string $when) {
        $timestamp = strtotime($when);
        if ($timestamp === false) {
            throw new ExpectationException("Could not parse time expression '$when'", $this->getSession());
        }
        $this->start_attempt($username, $quizname, $timestamp);
    }

    /**
     * Create N students and have them each begin a leaderboard attempt.
     * Generic count + "students" wording is used by the row-cap scenarios in
     * the sidebar summary feature.
     *
     * @Given /^(?P<count>\d+) students have begun a leaderboard attempt at quiz "(?P<quiz_name>(?:[^"]|\\")*)"$/
     *
     * @param int    $count
     * @param string $quizname
     */
    public function n_students_have_started_an_attempt(int $count, string $quizname) {
        global $DB;

        $quizid = $this->get_quiz_id($quizname);
        $quiz   = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);

        $generator = behat_util::get_data_generator();

        for ($i = 1; $i <= $count; $i++) {
            $username = 'bulkstudent' . $i . '_' . substr(md5(uniqid('', true)), 0, 6);
            $user = $generator->create_user([
                'username'  => $username,
                'firstname' => 'Bulk',
                'lastname'  => 'Student' . $i,
            ]);
            $generator->enrol_user($user->id, $course->id, 'student');

            $this->start_attempt($username, $quizname, null);
        }
    }

    // -------------------------------------------------------------------
    // Setup: answering questions (correctness + optional timestamp)
    // -------------------------------------------------------------------

    /**
     * Mark a question as answered correctly (full marks) for a user's current
     * attempt at the given quiz, using the live submission time.
     *
     * @Given /^question "(?P<question_name>(?:[^"]|\\")*)" is answered correctly by "(?P<username>(?:[^"]|\\")*)" in quiz "(?P<quiz_name>(?:[^"]|\\")*)"$/
     *
     * @param string $questionname
     * @param string $username
     * @param string $quizname
     */
    public function question_is_answered_correctly(string $questionname, string $username, string $quizname) {
        $this->submit_response($questionname, $username, $quizname, true, null);
    }

    /**
     * Mark a question as answered incorrectly (zero marks) for a user's
     * current attempt at the given quiz, using the live submission time.
     * This is the scenario that previously exposed the "zero marks show as
     * not-attempted" bug, so it is exercised heavily across the suite.
     *
     * @Given /^question "(?P<question_name>(?:[^"]|\\")*)" is answered incorrectly by "(?P<username>(?:[^"]|\\")*)" in quiz "(?P<quiz_name>(?:[^"]|\\")*)"$/
     *
     * @param string $questionname
     * @param string $username
     * @param string $quizname
     */
    public function question_is_answered_incorrectly(string $questionname, string $username, string $quizname) {
        $this->submit_response($questionname, $username, $quizname, false, null);
    }

    /**
     * Same as "answered correctly", but the graded step is timestamped at a
     * specific relative time, used by time-travel scenarios.
     *
     * @Given /^question "(?P<question_name>(?:[^"]|\\")*)" was answered correctly by "(?P<username>(?:[^"]|\\")*)" in quiz "(?P<quiz_name>(?:[^"]|\\")*)" at "(?P<when>(?:[^"]|\\")*)"$/
     *
     * @param string $questionname
     * @param string $username
     * @param string $quizname
     * @param string $when
     */
    public function question_was_answered_correctly_at(string $questionname, string $username, string $quizname, string $when) {
        $timestamp = strtotime($when);
        if ($timestamp === false) {
            throw new ExpectationException("Could not parse time expression '$when'", $this->getSession());
        }
        $this->submit_response($questionname, $username, $quizname, true, $timestamp);
    }

    /**
     * Same as "answered incorrectly", but at a specific relative time.
     *
     * @Given /^question "(?P<question_name>(?:[^"]|\\")*)" was answered incorrectly by "(?P<username>(?:[^"]|\\")*)" in quiz "(?P<quiz_name>(?:[^"]|\\")*)" at "(?P<when>(?:[^"]|\\")*)"$/
     *
     * @param string $questionname
     * @param string $username
     * @param string $quizname
     * @param string $when
     */
    public function question_was_answered_incorrectly_at(string $questionname, string $username, string $quizname, string $when) {
        $timestamp = strtotime($when);
        if ($timestamp === false) {
            throw new ExpectationException("Could not parse time expression '$when'", $this->getSession());
        }
        $this->submit_response($questionname, $username, $quizname, false, $timestamp);
    }

    // -------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------

    /**
     * Navigate directly to the standalone full-width leaderboard page for a
     * quiz (equivalent to a teacher clicking "View full leaderboard").
     *
     * @Given /^I view the full leaderboard for quiz "(?P<quiz_name>(?:[^"]|\\")*)"$/
     *
     * @param string $quizname
     */
    public function i_view_the_full_leaderboard_for_quiz(string $quizname) {
        $quizid = $this->get_quiz_id($quizname);
        $url = new moodle_url('/blocks/quizleaderboard/leaderboard.php', ['quizid' => $quizid]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    // -------------------------------------------------------------------
    // Time-travel interactions
    // -------------------------------------------------------------------

    /**
     * Tick the time-travel mode checkbox on, revealing and enabling the slider.
     *
     * @When /^I enable time-travel mode$/
     */
    public function i_enable_timetravel_mode() {
        $checkbox = $this->find('css', '#ql-timetravel-toggle');
        if (!$checkbox->isChecked()) {
            $checkbox->click();
        }
        $this->wait_for_pending_js();
    }

    /**
     * Untick the time-travel mode checkbox, reverting to the live leaderboard.
     *
     * @When /^I disable time-travel mode$/
     */
    public function i_disable_timetravel_mode() {
        $checkbox = $this->find('css', '#ql-timetravel-toggle');
        if ($checkbox->isChecked()) {
            $checkbox->click();
        }
        $this->wait_for_pending_js();
    }

    /**
     * Drag the time-travel slider to a specific number of minutes since the
     * earliest recorded activity, then wait for the AJAX-refreshed table.
     *
     * @When /^I set the leaderboard time-travel slider to minute (?P<minutes>\d+)$/
     *
     * @param int $minutes
     */
    public function i_set_the_slider_to_minute(int $minutes) {
        $session = $this->getSession();

        // Use getElementById via JavaScript rather than Mink's CSS finder,
        // because Mink (depending on the driver) may refuse to interact with
        // elements that have display:none — but the slider is inside a wrapper
        // that starts hidden and is shown by the toggle click. Even after the
        // toggle fires, there can be a brief rendering moment where Mink still
        // considers it hidden. JS always finds the element regardless.
        $session->executeScript(
            "var el = document.getElementById('ql-timetravel-slider');" .
            "if (!el) { throw new Error('ql-timetravel-slider not found'); }" .
            "el.value = " . (int)$minutes . ";" .
            "el.dispatchEvent(new Event('input', { bubbles: true }));"
        );

        $this->wait_for_pending_js();
        // Wait for the debounce (250ms) + AJAX round-trip.
        $session->wait(1500, false);
    }

    // -------------------------------------------------------------------
    // Sorting interactions
    // -------------------------------------------------------------------

    /**
     * Click a leaderboard column header by its visible label to toggle sort.
     *
     * @When /^I click on the "(?P<label>(?:[^"]|\\")*)" leaderboard column header$/
     *
     * @param string $label
     */
    public function i_click_leaderboard_column_header(string $label) {
        $th = $this->find_leaderboard_header($label);
        $th->click();
        $this->wait_for_pending_js();
    }

    /**
     * Press a key while a leaderboard column header has focus, for keyboard
     * accessibility testing.
     *
     * @When /^I press the "(?P<key>(?:[^"]|\\")*)" key while focused on the "(?P<label>(?:[^"]|\\")*)" leaderboard column header$/
     *
     * @param string $key
     * @param string $label
     */
    public function i_press_key_on_leaderboard_header(string $key, string $label) {
        $th = $this->find_leaderboard_header($label);

        // Mink's keyPress() triggers the deprecated 'keypress' event, but our
        // leaderboard.js listens for 'keydown'. Dispatch a real KeyboardEvent
        // via JavaScript to match what a real browser produces when the user
        // presses Enter on a focused element.
        $keynames = ['Enter' => 'Enter', 'Space' => ' '];
        $keyname  = $keynames[$key] ?? $key;

        $this->getSession()->executeScript(
            "var el = document.querySelector('table.ql-table thead th[data-colidx=\"" .
            (int)$th->getAttribute('data-colidx') . "\"]');" .
            "if (el) { el.dispatchEvent(new KeyboardEvent('keydown', {key: " . json_encode($keyname) . ", bubbles: true})); }"
        );

        $this->wait_for_pending_js();
    }

    // -------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------

    /**
     * Assert that a specific student's cell for a given question number shows
     * the expected text and CSS class.
     *
     * @Then /^the leaderboard cell for "(?P<student_name>(?:[^"]|\\")*)" question (?P<qnum>\d+) should show "(?P<text>(?:[^"]|\\")*)" with class "(?P<cssclass>(?:[^"]|\\")*)"$/
     *
     * @param string $studentname
     * @param int    $qnum
     * @param string $expectedtext
     * @param string $cssclass
     */
    public function leaderboard_cell_should_show(string $studentname, int $qnum, string $expectedtext, string $cssclass) {
        $cell = $this->find_leaderboard_question_cell($studentname, $qnum);

        $actualtext = trim($cell->getText());
        if ($actualtext !== trim($expectedtext)) {
            throw new ExpectationException(
                "Expected leaderboard cell for '$studentname' Q$qnum to show '$expectedtext' but found '$actualtext'",
                $this->getSession()
            );
        }

        $actualclass = $cell->getAttribute('class') ?? '';
        if (strpos($actualclass, $cssclass) === false) {
            throw new ExpectationException(
                "Expected leaderboard cell for '$studentname' Q$qnum to have class '$cssclass' but found '$actualclass'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that a specific student's cell for a given question does NOT
     * carry a given CSS class (used to assert e.g. a zero-mark cell is not
     * also marked as "not attempted").
     *
     * @Then /^the leaderboard cell for "(?P<student_name>(?:[^"]|\\")*)" question (?P<qnum>\d+) should not have class "(?P<cssclass>(?:[^"]|\\")*)"$/
     *
     * @param string $studentname
     * @param int    $qnum
     * @param string $cssclass
     */
    public function leaderboard_cell_should_not_have_class(string $studentname, int $qnum, string $cssclass) {
        $cell = $this->find_leaderboard_question_cell($studentname, $qnum);
        $actualclass = $cell->getAttribute('class') ?? '';
        if (strpos($actualclass, $cssclass) !== false) {
            throw new ExpectationException(
                "Expected leaderboard cell for '$studentname' Q$qnum NOT to have class '$cssclass' but it did ('$actualclass')",
                $this->getSession()
            );
        }
    }

    /**
     * Assert a student's displayed total mark.
     *
     * @Then /^the leaderboard total for "(?P<student_name>(?:[^"]|\\")*)" should be "(?P<expected>(?:[^"]|\\")*)"$/
     *
     * @param string $studentname
     * @param string $expected
     */
    public function leaderboard_total_should_be(string $studentname, string $expected) {
        $row = $this->find_leaderboard_row($studentname);
        $cell = $row->find('css', 'td.ql-total');
        if ($cell === null) {
            throw new ExpectationException("No total cell found for '$studentname'", $this->getSession());
        }
        $actual = trim($cell->getText());
        if ($actual !== trim($expected)) {
            throw new ExpectationException(
                "Expected total for '$studentname' to be '$expected' but found '$actual'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert a student's displayed percentage.
     *
     * @Then /^the leaderboard percentage for "(?P<student_name>(?:[^"]|\\")*)" should be "(?P<expected>(?:[^"]|\\")*)"$/
     *
     * @param string $studentname
     * @param string $expected
     */
    public function leaderboard_percentage_should_be(string $studentname, string $expected) {
        $row = $this->find_leaderboard_row($studentname);
        $cell = $row->find('css', 'td.ql-pct');
        if ($cell === null) {
            throw new ExpectationException("No percentage cell found for '$studentname'", $this->getSession());
        }
        $actual = trim($cell->getText());
        if ($actual !== trim($expected)) {
            throw new ExpectationException(
                "Expected percentage for '$studentname' to be '$expected' but found '$actual'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert which student appears in a given (1-indexed) row of the leaderboard table.
     *
     * @Then /^row (?P<rownum>\d+) of the leaderboard table should contain "(?P<text>(?:[^"]|\\")*)"$/
     *
     * @param int    $rownum
     * @param string $text
     */
    public function row_n_should_contain(int $rownum, string $text) {
        $table = $this->find('css', 'table.ql-table');
        $rows  = $table->findAll('css', 'tbody tr');

        if (!isset($rows[$rownum - 1])) {
            throw new ExpectationException("Leaderboard table does not have a row $rownum", $this->getSession());
        }

        $rowtext = $rows[$rownum - 1]->getText();
        if (strpos($rowtext, $text) === false) {
            throw new ExpectationException(
                "Expected row $rownum to contain '$text' but found '$rowtext'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert the header for a given column is marked as sorted ascending.
     *
     * @Then /^the "(?P<label>(?:[^"]|\\")*)" leaderboard column header should be marked as sorted ascending$/
     *
     * @param string $label
     */
    public function header_should_be_sorted_ascending(string $label) {
        $th = $this->find_leaderboard_header($label);
        $ariasort = $th->getAttribute('aria-sort');
        if ($ariasort !== 'ascending') {
            throw new ExpectationException(
                "Expected '$label' header aria-sort to be 'ascending' but found '$ariasort'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert the header for a given column is marked as sorted descending.
     *
     * @Then /^the "(?P<label>(?:[^"]|\\")*)" leaderboard column header should be marked as sorted descending$/
     *
     * @param string $label
     */
    public function header_should_be_sorted_descending(string $label) {
        $th = $this->find_leaderboard_header($label);
        $ariasort = $th->getAttribute('aria-sort');
        if ($ariasort !== 'descending') {
            throw new ExpectationException(
                "Expected '$label' header aria-sort to be 'descending' but found '$ariasort'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert the header for a given column is NOT currently marked as sorted.
     *
     * @Then /^the "(?P<label>(?:[^"]|\\")*)" leaderboard column header should not be marked as sorted$/
     *
     * @param string $label
     */
    public function header_should_not_be_marked_sorted(string $label) {
        $th = $this->find_leaderboard_header($label);
        $ariasort = $th->getAttribute('aria-sort');
        if ($ariasort !== 'none') {
            throw new ExpectationException(
                "Expected '$label' header aria-sort to be 'none' but found '$ariasort'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert the time-travel slider is currently disabled.
     *
     * @Then /^the leaderboard slider should be disabled$/
     */
    public function leaderboard_slider_should_be_disabled() {
        $disabled = $this->getSession()->evaluateScript(
            "(function() { var el = document.getElementById('ql-timetravel-slider'); return el ? el.disabled : null; })()"
        );
        if ($disabled === null) {
            throw new ExpectationException('The leaderboard time-travel slider was not found in the DOM', $this->getSession());
        }
        if (!$disabled) {
            throw new ExpectationException('Expected the leaderboard time-travel slider to be disabled', $this->getSession());
        }
    }

    /**
     * Assert the time-travel slider is currently enabled.
     *
     * @Then /^the leaderboard slider should be enabled$/
     */
    public function leaderboard_slider_should_be_enabled() {
        $disabled = $this->getSession()->evaluateScript(
            "(function() { var el = document.getElementById('ql-timetravel-slider'); return el ? el.disabled : null; })()"
        );
        if ($disabled === null) {
            throw new ExpectationException('The leaderboard time-travel slider was not found in the DOM', $this->getSession());
        }
        if ($disabled) {
            throw new ExpectationException('Expected the leaderboard time-travel slider to be enabled', $this->getSession());
        }
    }

    /**
     * Assert the named checkbox (matched by its visible label) is NOT
     * currently ticked.
     *
     * This is implemented as our own step, scoped to a generic
     * "<label>" "checkbox" pairing, rather than relying on a Moodle core
     * step of the same shape — core does not ship a negative ("should not be
     * checked") counterpart to its positive checkbox-state assertions on all
     * supported versions, and defining our own avoids both the missing-step
     * error and any risk of a future ambiguous-match collision like the one
     * we hit with "has started an attempt at quiz".
     *
     * Currently only resolves the "Time-travel mode" checkbox used by this
     * plugin's own UI, since that is the only checkbox these scenarios assert
     * against; extend the label map below if more are needed later.
     *
     * @Then /^the "(?P<label>(?:[^"]|\\")*)" "checkbox" should not be checked$/
     *
     * @param string $label
     */
    public function the_named_checkbox_should_not_be_checked(string $label) {
        $selector = $this->resolve_checkbox_selector($label);
        $checkbox = $this->find('css', $selector);

        if ($checkbox->isChecked()) {
            throw new ExpectationException(
                "Expected the '$label' checkbox to be unchecked, but it was checked",
                $this->getSession()
            );
        }
    }

    /**
     * Assert the named checkbox (matched by its visible label) IS currently
     * ticked. Provided alongside the "should not be checked" step above for
     * symmetry, in case a future scenario needs the positive assertion.
     *
     * @Then /^the "(?P<label>(?:[^"]|\\")*)" "checkbox" should be checked$/
     *
     * @param string $label
     */
    public function the_named_checkbox_should_be_checked(string $label) {
        $selector = $this->resolve_checkbox_selector($label);
        $checkbox = $this->find('css', $selector);

        if (!$checkbox->isChecked()) {
            throw new ExpectationException(
                "Expected the '$label' checkbox to be checked, but it was unchecked",
                $this->getSession()
            );
        }
    }

    /**
     * Map a checkbox's visible label to a CSS selector.
     *
     * @param string $label
     * @return string CSS selector.
     */
    protected function resolve_checkbox_selector(string $label): string {
        $map = [
            'Time-travel mode' => '#ql-timetravel-toggle',
        ];

        if (!isset($map[$label])) {
            throw new ExpectationException(
                "Unknown checkbox label '$label' — add it to resolve_checkbox_selector() in behat_block_quizleaderboard.php",
                $this->getSession()
            );
        }

        return $map[$label];
    }

    /**
     * Assert that the Nth column header in the leaderboard table shows a
     * specific label — used to verify that description slots show a plain
     * dash and that real question slots carry the correct sequential number.
     *
     * $slot is 1-indexed and refers to the physical slot position (not the
     * question number), so a description at slot 1 is checked with slotnum=1.
     *
     * @Then /^the leaderboard column header at slot (?P<slotnum>\d+) should show "(?P<expected>(?:[^"]|\\")*)"$/
     *
     * @param int    $slotnum  1-indexed physical slot position in the table.
     * @param string $expected The expected visible text of the header cell.
     */
    public function leaderboard_column_header_at_slot_should_show(int $slotnum, string $expected) {
        $table = $this->find('css', 'table.ql-table');

        // Only select question/description column headers (ql-col-q class),
        // not the fixed columns (rank #, Student, ID, Total, %) which precede them.
        $headers = $table->findAll('css', 'thead th.ql-col-q');

        if (!isset($headers[$slotnum - 1])) {
            throw new ExpectationException(
                "The leaderboard table does not have a question/description column at slot $slotnum",
                $this->getSession()
            );
        }

        $th   = $headers[$slotnum - 1];
        $html = $th->getHtml();

        // Strip subheader and sort icon spans to get just the main label text.
        $mainhtml = preg_replace('/<span[^>]*class="[^"]*ql-subheader[^"]*"[^>]*>.*?<\/span>/s', '', $html);
        $mainhtml = preg_replace('/<span[^>]*class="[^"]*ql-sort-icon[^"]*"[^>]*>.*?<\/span>/s', '', $mainhtml);
        $actual   = trim(preg_replace('/\s+/', ' ', strip_tags($mainhtml)));

        if ($actual !== $expected) {
            throw new ExpectationException(
                "Expected question column header at slot $slotnum to show '$expected' but found '$actual'",
                $this->getSession()
            );
        }
    }

    /**
     * Convenience step: asserts that the header at slot N is a description
     * placeholder (the bare "-" dash) rather than a question number.
     *
     * @Then /^the leaderboard column header at slot (?P<slotnum>\d+) should show "-" for a description$/
     *
     * @param int $slotnum
     */
    public function leaderboard_column_header_at_slot_is_description(int $slotnum) {
        $this->leaderboard_column_header_at_slot_should_show($slotnum, '-');

        // Also verify the column carries the ql-col-description CSS class.
        $table   = $this->find('css', 'table.ql-table');
        $headers = $table->findAll('css', 'thead th.ql-col-q');
        $th      = $headers[$slotnum - 1];
        $class   = $th->getAttribute('class') ?? '';

        if (strpos($class, 'ql-col-description') === false) {
            throw new ExpectationException(
                "Expected column header at slot $slotnum to have class 'ql-col-description' but found '$class'",
                $this->getSession()
            );
        }
    }

    /**
     * Find the Nth <td> in a student's leaderboard row by physical slot
     * position (1-indexed). This is intentionally different from
     * find_leaderboard_question_cell(), which finds the Nth *scored* question
     * cell (skipping descriptions) — here we address the raw column index.
     *
     * "Slot" includes all columns: rank, name, id, total, %, then slot 1...N.
     * The offset for the first question column depends on which fixed columns
     * are shown. We locate the right td by counting from the first ql-q cell
     * (which is always the first question/description column, regardless of
     * how many fixed columns precede it).
     *
     * @param string $studentname
     * @param int    $slotnum 1-indexed question slot position.
     * @return \Behat\Mink\Element\NodeElement
     */
    protected function find_slot_cell_in_row(string $studentname, int $slotnum) {
        $row    = $this->find_leaderboard_row($studentname);
        $qcells = $row->findAll('css', 'td.ql-q, td.ql-description');

        if (!isset($qcells[$slotnum - 1])) {
            throw new ExpectationException(
                "Row for '$studentname' does not have a cell at slot $slotnum",
                $this->getSession()
            );
        }

        return $qcells[$slotnum - 1];
    }

    /**
     * Assert that the description cell at a given physical slot for a named
     * student shows the expected text.
     *
     * @Then /^the leaderboard description cell at slot (?P<slotnum>\d+) for "(?P<student_name>(?:[^"]|\\")*)" should show "(?P<expected>(?:[^"]|\\")*)"$/
     *
     * @param int    $slotnum
     * @param string $studentname
     * @param string $expected
     */
    public function leaderboard_description_cell_should_show(int $slotnum, string $studentname, string $expected) {
        $cell   = $this->find_slot_cell_in_row($studentname, $slotnum);
        $actual = trim($cell->getText());

        if ($actual !== $expected) {
            throw new ExpectationException(
                "Expected description cell at slot $slotnum for '$studentname' to show '$expected' but found '$actual'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that the description cell at a given physical slot carries a CSS class.
     *
     * @Then /^the leaderboard description cell at slot (?P<slotnum>\d+) for "(?P<student_name>(?:[^"]|\\")*)" should have class "(?P<cssclass>(?:[^"]|\\")*)"$/
     *
     * @param int    $slotnum
     * @param string $studentname
     * @param string $cssclass
     */
    public function leaderboard_description_cell_should_have_class(int $slotnum, string $studentname, string $cssclass) {
        $cell        = $this->find_slot_cell_in_row($studentname, $slotnum);
        $actualclass = $cell->getAttribute('class') ?? '';

        if (strpos($actualclass, $cssclass) === false) {
            throw new ExpectationException(
                "Expected description cell at slot $slotnum for '$studentname' to have class '$cssclass' but found '$actualclass'",
                $this->getSession()
            );
        }
    }

    /**
     * Assert that the description cell at a given physical slot does NOT carry a CSS class.
     *
     * @Then /^the leaderboard description cell at slot (?P<slotnum>\d+) for "(?P<student_name>(?:[^"]|\\")*)" should not have class "(?P<cssclass>(?:[^"]|\\")*)"$/
     *
     * @param int    $slotnum
     * @param string $studentname
     * @param string $cssclass
     */
    public function leaderboard_description_cell_should_not_have_class(int $slotnum, string $studentname, string $cssclass) {
        $cell        = $this->find_slot_cell_in_row($studentname, $slotnum);
        $actualclass = $cell->getAttribute('class') ?? '';

        if (strpos($actualclass, $cssclass) !== false) {
            throw new ExpectationException(
                "Expected description cell at slot $slotnum for '$studentname' NOT to have class '$cssclass' but it did",
                $this->getSession()
            );
        }
    }

    /**
     * Assert the number of rows in the leaderboard table inside a given block.
     *
     * @Then /^the number of rows in the leaderboard table in the "(?P<block_name>(?:[^"]|\\")*)" "(?:block)" should be (?P<count>\d+)$/
     *
     * @param string $blockname
     * @param int    $count
     */
    public function leaderboard_table_row_count_in_block(string $blockname, int $count) {
        $blocknode = $this->find('css', "[data-block='" . str_replace('block_', '', $blockname) . "']");
        $table = $blocknode->find('css', 'table.ql-table');
        if ($table === null) {
            throw new ExpectationException("No leaderboard table found in block '$blockname'", $this->getSession());
        }
        $rows = $table->findAll('css', 'tbody tr');
        if (count($rows) !== $count) {
            throw new ExpectationException(
                "Expected $count rows in the leaderboard table but found " . count($rows),
                $this->getSession()
            );
        }
    }

    /**
     * Assert that a named link's text appears earlier in the rendered HTML of
     * a given block than a separate piece of free text.
     *
     * Implemented as our own step rather than relying on a generic Moodle
     * core "X should appear before Y" step, because core's version expects
     * both arguments to be selector-type pairs (e.g. "X" "link" should
     * appear before "Y" "text") — passing arbitrary free text like a
     * student's name as the second argument is interpreted as a selector
     * type name and fails with "The "..." selector type does not exist."
     * This step instead just compares raw character offsets within the
     * block's HTML, which is simpler and sufficient for our purposes (e.g.
     * asserting the "View full leaderboard" link renders above the table).
     *
     * @Then /^the "(?P<linktext>(?:[^"]|\\")*)" link should appear before "(?P<followingtext>(?:[^"]|\\")*)" in the "(?P<blockname>(?:[^"]|\\")*)" "(?:block)"$/
     *
     * @param string $linktext
     * @param string $followingtext
     * @param string $blockname
     */
    public function link_should_appear_before_text_in_block(string $linktext, string $followingtext, string $blockname) {
        $blocknode = $this->find('css', "[data-block='" . str_replace('block_', '', $blockname) . "']");
        $html = $blocknode->getHtml();

        $linkpos = strpos($html, $linktext);
        $textpos = strpos($html, $followingtext);

        if ($linkpos === false) {
            throw new ExpectationException("Could not find link text '$linktext' in block '$blockname'", $this->getSession());
        }
        if ($textpos === false) {
            throw new ExpectationException("Could not find text '$followingtext' in block '$blockname'", $this->getSession());
        }
        if ($linkpos >= $textpos) {
            throw new ExpectationException(
                "Expected '$linktext' to appear before '$followingtext' in block '$blockname', but it did not",
                $this->getSession()
            );
        }
    }

    // -------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------

    /**
     * Resolve and cache a quiz's id from its name.
     *
     * @param string $quizname
     * @return int
     */
    protected function get_quiz_id(string $quizname): int {
        global $DB;

        if (isset($this->quizidcache[$quizname])) {
            return $this->quizidcache[$quizname];
        }

        $quiz = $DB->get_record('quiz', ['name' => $quizname], '*', MUST_EXIST);
        $this->quizidcache[$quizname] = (int)$quiz->id;
        return (int)$quiz->id;
    }

    /**
     * Start (or reuse) an in-progress attempt for a user at a quiz, optionally
     * forcing a specific timestart.
     *
     * IMPORTANT: quiz_create_attempt()/quiz_start_new_attempt() are written to
     * run inside a real logged-in request for the acting student — they read
     * and rely on global $USER in places. Since this step runs in the Behat
     * test process (not inside the Mink-driven browser session), we must
     * explicitly switch $USER/session to the target student for the duration
     * of the call, then restore whatever user/session was active before —
     * otherwise a subsequent "I log in as ..." step in the same scenario can
     * end up effectively still authenticated as this student (or as no one),
     * which is what was producing the "You are not logged in" page snapshots.
     *
     * @param string   $username
     * @param string   $quizname
     * @param int|null $timestart Unix timestamp, or null for "now".
     * @return int The quiz attempt id.
     */
    /**
     * Start a quiz attempt by writing directly to the database, avoiding all
     * Moodle session/authentication APIs (quiz_attempt::create, quiz_settings::create,
     * quiz_create_attempt, etc.) which internally call require_login() or check
     * $_SESSION, corrupting the Mink browser session and causing "not logged in"
     * failures on subsequent navigation steps.
     *
     * We write the minimum required rows:
     *   question_usages        - the usage record
     *   question_attempts      - one row per quiz slot
     *   question_attempt_steps - the initial 'todo' step for each question
     *   quiz_attempts          - the attempt header row
     *
     * @param string   $username
     * @param string   $quizname
     * @param int|null $timestart
     * @return int The new quiz_attempts.id
     */
    protected function start_attempt(string $username, string $quizname, ?int $timestart): int {
        global $DB;

        $user   = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $quizid = $this->get_quiz_id($quizname);
        $quiz   = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $cm     = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);

        $cachekey = $username . '|' . $quizname;
        if (isset($this->attemptidcache[$cachekey])) {
            return $this->attemptidcache[$cachekey];
        }

        $timenow = $timestart ?? time();

        // 1. Create the question_usages row.
        $context = context_module::instance($cm->id);
        $usage = new stdClass();
        $usage->component        = 'mod_quiz';
        $usage->contextid        = $context->id;
        $usage->preferredbehaviour = $quiz->preferredbehaviour;
        $usage->id = $DB->insert_record('question_usages', $usage);

        // 2. For each quiz slot, create a question_attempts row + initial 'todo' step.
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
        foreach ($slots as $slot) {
            $qa = new stdClass();
            $qa->questionusageid   = $usage->id;
            $qa->slot              = $slot->slot;
            $qa->behaviour         = $quiz->preferredbehaviour;
            $qa->questionid        = $this->get_question_id_for_slot($slot);
            $qa->variant           = 1;
            $qa->maxmark           = $slot->maxmark;
            $qa->minfraction       = 0;
            $qa->maxfraction       = 1;
            $qa->flagged           = 0;
            $qa->questionsummary   = '';
            $qa->rightanswer       = '';
            $qa->responsesummary   = '';
            $qa->timemodified      = $timenow;
            $qaid = $DB->insert_record('question_attempts', $qa);

            // Initial 'todo' step (sequence 0).
            $step = new stdClass();
            $step->questionattemptid = $qaid;
            $step->sequencenumber    = 0;
            $step->state             = 'todo';
            $step->fraction          = null;
            $step->timecreated       = $timenow;
            $step->userid            = $user->id;
            $DB->insert_record('question_attempt_steps', $step);
        }

        // 3. Create the quiz_attempts row.
        $attempt = new stdClass();
        $attempt->quiz           = $quiz->id;
        $attempt->userid         = $user->id;
        $attempt->attempt        = 1;
        $attempt->uniqueid       = $usage->id;
        $attempt->layout         = implode(',', array_column(array_values($slots), 'slot')) . ',0';
        $attempt->currentpage    = 0;
        $attempt->preview        = 0;
        $attempt->state          = 'inprogress';
        $attempt->timestart      = $timenow;
        $attempt->timecheckstate = null;
        $attempt->timemodified   = $timenow;
        $attempt->timefinish     = 0;
        $attempt->sumgrades      = null;
        $attempt->gradednotificationsenttime = null;
        $attemptid = $DB->insert_record('quiz_attempts', $attempt);

        $this->attemptidcache[$cachekey] = $attemptid;
        return $attemptid;
    }

    /**
     * Get the questionid for a quiz slot using the Moodle 5.0+ question bank
     * schema (question_references → question_versions), which is always present
     * on the versions this plugin supports.
     *
     * @param \stdClass $slot quiz_slots row
     * @return int
     */
    protected function get_question_id_for_slot(\stdClass $slot): int {
        global $DB;

        $sql = "SELECT qv.questionid
                  FROM {question_references} qr
                  JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
                 WHERE qr.itemid = :slotid
                   AND qr.component = 'mod_quiz'
                   AND qr.questionarea = 'slot'
              ORDER BY qv.version DESC";

        $rec = $DB->get_record_sql($sql, ['slotid' => $slot->id], IGNORE_MULTIPLE);
        if ($rec) {
            return (int)$rec->questionid;
        }

        throw new \coding_exception("Cannot resolve questionid for slot {$slot->id}");
    }

    /**
     * Temporarily swap the global $USER to the given user for the duration of
     * $callback, then restore whoever was active before.
     *
     * DELIBERATELY does NOT call \core\session\manager::set_user() or
     * init_empty_session(). Those methods manipulate $_SESSION, which is
     * the same PHP session that Mink's browser is authenticated against. If
     * we touch it, the browser loses its login cookie and every subsequent
     * navigation step sees "You are not logged in." — which is exactly the
     * symptom we were seeing.
     *
     * For the DB operations we need (creating quiz attempts, submitting
     * responses), only $USER->id and $USER->sesskey matter. Swapping the
     * global directly is sufficient and safe, as long as we restore it
     * in a finally block so no exception can leave $USER in the wrong state.
     *
     * @param \stdClass $user     The user to act as for the duration of $callback.
     * @param callable  $callback Receives no arguments; its return value is passed through.
     * @return mixed Whatever $callback returns.
     */
    protected function as_user(\stdClass $user, callable $callback) {
        global $USER;

        $previoususer = $USER;

        // Directly assign — no session manipulation.
        $USER = $user;

        try {
            $result = $callback();
        } finally {
            // Always restore, even if $callback threw an exception.
            $USER = $previoususer;
        }

        return $result;
    }

    /**
     * Submit a graded response by writing directly to the database.
     *
     * Avoids quiz_attempt::create() and process_submitted_actions() which
     * internally call require_login() / sesskey checks, corrupting the Mink
     * browser session.
     *
     * For a truefalse question in adaptive mode, grading a response writes:
     *   question_attempt_steps     - a new step with state gradedright/gradedwrong
     *   question_attempt_step_data - the :answer value for that step
     * and updates:
     *   question_attempts          - responsesummary, rightanswer, timemodified
     *   quiz_attempts              - sumgrades, timemodified
     *
     * @param string   $questionname
     * @param string   $username
     * @param string   $quizname
     * @param bool     $correct
     * @param int|null $when
     */
    protected function submit_response(string $questionname, string $username, string $quizname, bool $correct, ?int $when) {
        global $DB;

        $cachekey = $username . '|' . $quizname;
        if (!isset($this->attemptidcache[$cachekey])) {
            $this->start_attempt($username, $quizname, $when);
        }
        $attemptid  = $this->attemptidcache[$cachekey];
        $timestamp  = $when ?? time();
        $user       = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $attempt    = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);

        // Find the question_attempts row for this slot.
        $slot = $this->get_slot_for_question_by_name($attempt, $questionname);
        $qa = $DB->get_record('question_attempts', [
            'questionusageid' => $attempt->uniqueid,
            'slot'            => $slot,
        ], '*', MUST_EXIST);

        // Look up the truefalse answer IDs (trueanswer / falseanswer).
        // qtype_truefalse stores two records in question_answers:
        //   fraction=1 => the correct answer, fraction=0 => the wrong answer.
        $answers = $DB->get_records('question_answers', ['question' => $qa->questionid], 'fraction DESC');
        $correctanswerid = null;
        $wronganswerid   = null;
        foreach ($answers as $ans) {
            if ((float)$ans->fraction >= 1.0 && $correctanswerid === null) {
                $correctanswerid = (int)$ans->id;
            } else if ($wronganswerid === null) {
                $wronganswerid = (int)$ans->id;
            }
        }
        if (!$correctanswerid || !$wronganswerid) {
            throw new ExpectationException(
                "Could not find true/false answer IDs for question in slot $slot",
                $this->getSession()
            );
        }

        $chosenanswerid = $correct ? $correctanswerid : $wronganswerid;
        $fraction       = $correct ? 1.0 : 0.0;
        $state          = $correct ? 'gradedright' : 'gradedwrong';

        // What sequence number comes next?
        $maxseq = $DB->get_field_sql(
            'SELECT MAX(sequencenumber) FROM {question_attempt_steps} WHERE questionattemptid = ?',
            [$qa->id]
        );
        $nextseq = ((int)$maxseq) + 1;

        // Write the graded step.
        $step = new stdClass();
        $step->questionattemptid = $qa->id;
        $step->sequencenumber    = $nextseq;
        $step->state             = $state;
        $step->fraction          = $fraction;
        $step->timecreated       = $timestamp;
        $step->userid            = $user->id;
        $stepid = $DB->insert_record('question_attempt_steps', $step);

        // Write the step data (the chosen answer).
        $stepdata = new stdClass();
        $stepdata->attemptstepid = $stepid;
        $stepdata->name          = ':answer';
        $stepdata->value         = (string)$chosenanswerid;
        $DB->insert_record('question_attempt_step_data', $stepdata);

        // In adaptive mode Moodle also adds a post-grading 'todo' step so the
        // student can try again. Write that too so our step history matches what
        // the real quiz would produce — the leaderboard_service correctly ignores
        // this trailing todo when finding the most recent graded step.
        $todostep = new stdClass();
        $todostep->questionattemptid = $qa->id;
        $todostep->sequencenumber    = $nextseq + 1;
        $todostep->state             = 'todo';
        $todostep->fraction          = null;
        $todostep->timecreated       = $timestamp;
        $todostep->userid            = $user->id;
        $DB->insert_record('question_attempt_steps', $todostep);

        // Update question_attempts summary fields.
        $DB->set_field('question_attempts', 'responsesummary', $correct ? 'True' : 'False', ['id' => $qa->id]);
        $DB->set_field('question_attempts', 'timemodified', $timestamp, ['id' => $qa->id]);

        // Recalculate and update sumgrades on quiz_attempts.
        $sumgrades = $DB->get_field_sql(
            "SELECT COALESCE(SUM(
                CASE WHEN latest.fraction IS NOT NULL
                     THEN latest.fraction * qa2.maxmark
                     ELSE 0 END
             ), 0)
               FROM {question_attempts} qa2
               JOIN (
                   SELECT qas2.questionattemptid, qas2.fraction
                     FROM {question_attempt_steps} qas2
                     JOIN (
                         SELECT questionattemptid, MAX(sequencenumber) AS maxseq
                           FROM {question_attempt_steps}
                          WHERE state IN ('gradedright','gradedwrong','gradedpartial',
                                          'mangrright','mangrwrong','mangrpartial','complete')
                         GROUP BY questionattemptid
                     ) lf ON lf.questionattemptid = qas2.questionattemptid
                          AND qas2.sequencenumber = lf.maxseq
               ) latest ON latest.questionattemptid = qa2.id
              WHERE qa2.questionusageid = ?",
            [$attempt->uniqueid]
        );
        $DB->set_field('quiz_attempts', 'sumgrades', (float)$sumgrades, ['id' => $attemptid]);
        $DB->set_field('quiz_attempts', 'timemodified', $timestamp, ['id' => $attemptid]);
    }


    /**
     * Find the slot number for a named question within a quiz attempt.
     *
     * @param \stdClass $attemptrecord The quiz_attempts DB record.
     * @param string    $questionname
     * @return int slot number
     */
    protected function get_slot_for_question_by_name(\stdClass $attemptrecord, string $questionname): int {
        global $DB;

        // Strategy 1: question_attempts.questionid → questions.name (modern Moodle 4.x).
        $sql = "SELECT qa.slot
                  FROM {question_attempts} qa
                  JOIN {question} q ON q.id = qa.questionid
                 WHERE qa.questionusageid = :usageid
                   AND q.name = :qname";
        $record = $DB->get_record_sql($sql, [
            'usageid' => $attemptrecord->uniqueid,
            'qname'   => $questionname,
        ], IGNORE_MISSING);
        if ($record) {
            return (int)$record->slot;
        }

        // Strategy 2: go via quiz_slots → question_references → question_versions
        // → questions (needed when questionid on question_attempts is stored
        // differently, or when the slot order matches the question name).
        $quiz = $DB->get_record('quiz', ['id' => $attemptrecord->quiz], '*', MUST_EXIST);
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
        foreach ($slots as $slot) {
            try {
                $qid = $this->get_question_id_for_slot($slot);
                $q = $DB->get_record('questions', ['id' => $qid], 'id, name', IGNORE_MISSING);
                if ($q && $q->name === $questionname) {
                    return (int)$slot->slot;
                }
            } catch (\Exception $e) {
                // Continue to next slot.
                continue;
            }
        }

        throw new ExpectationException(
            "Question '$questionname' not found in quiz attempt {$attemptrecord->id}",
            $this->getSession()
        );
    }

    /**
     * Backdate the most recent graded question_attempt_steps row for a given
     * slot within a quiz attempt to a specific timestamp.
     *
     * @param \stdClass $attemptrecord
     * @param int       $slot
     * @param int       $timestamp
     */
    protected function backdate_graded_step_for_slot(\stdClass $attemptrecord, int $slot, int $timestamp) {
        global $DB;

        $qa = $DB->get_record('question_attempts', [
            'questionusageid' => $attemptrecord->uniqueid,
            'slot'            => $slot,
        ], '*', MUST_EXIST);

        $answeredstates = [
            'complete', 'invalid', 'gradedright', 'gradedwrong', 'gradedpartial',
            'mangrright', 'mangrwrong', 'mangrpartial', 'gave_up',
        ];
        list($statesql, $stateparams) = $DB->get_in_or_equal($answeredstates, SQL_PARAMS_NAMED, 'state');

        $sql = "SELECT id
                  FROM {question_attempt_steps}
                 WHERE questionattemptid = :qaid
                   AND state $statesql
              ORDER BY sequencenumber DESC";

        $params = array_merge(['qaid' => $qa->id], $stateparams);
        $laststep = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);

        if ($laststep) {
            $DB->set_field('question_attempt_steps', 'timecreated', $timestamp, ['id' => $laststep->id]);
        }
    }

    /**
     * Locate a leaderboard table header <th> element by its visible text label.
     *
     * Some headers (e.g. the Total column) render as two lines — a main label
     * plus a smaller "Out of N" subheader — so getText() returns something
     * like "Total Out of 20" rather than just "Total". To keep feature files
     * able to refer to headers by their simple main label regardless of any
     * subheader text, this matches either the full trimmed text exactly, or
     * just its first line/word-wrapped segment (the text before the first
     * newline, which corresponds to the main label rendered before the <br>).
     *
     * @param string $label
     * @return \Behat\Mink\Element\NodeElement
     */
    protected function find_leaderboard_header(string $label) {
        $table = $this->find('css', 'table.ql-table');
        $headers = $table->findAll('css', 'thead th');

        foreach ($headers as $th) {
            // getText() on a multi-line header like:
            //   <th>Total<br><span class="ql-subheader">Out of 20</span></th>
            // returns something like "Total Out of 20" or "TotalOut of 20"
            // depending on the browser driver, so simple string matching
            // against the full text is unreliable.
            //
            // Instead: strip out the ql-subheader span from the innerHTML,
            // then compare the remaining plain text — this isolates just the
            // main label (e.g. "Total") regardless of what the subheader says.
            $html = $th->getHtml();

            // Remove the subheader span (and its contents) from the HTML.
            $mainhtml = preg_replace('/<span[^>]*class="[^"]*ql-subheader[^"]*"[^>]*>.*?<\/span>/s', '', $html);

            // Also remove the sort-icon span.
            $mainhtml = preg_replace('/<span[^>]*class="[^"]*ql-sort-icon[^"]*"[^>]*>.*?<\/span>/s', '', $mainhtml);

            // Strip all remaining tags and collapse whitespace.
            $maintext = trim(preg_replace('/\s+/', ' ', strip_tags($mainhtml)));

            if ($maintext === $label) {
                return $th;
            }
        }

        throw new ExpectationException("No leaderboard column header found with label '$label'", $this->getSession());
    }

    /**
     * Locate the <tr> in the leaderboard table for a given student's display name.
     *
     * @param string $studentname
     * @return \Behat\Mink\Element\NodeElement
     */
    protected function find_leaderboard_row(string $studentname) {
        $table = $this->find('css', 'table.ql-table');
        $rows  = $table->findAll('css', 'tbody tr');

        foreach ($rows as $row) {
            $namecell = $row->find('css', 'td.ql-name');
            if ($namecell !== null && trim($namecell->getText()) === $studentname) {
                return $row;
            }
        }

        throw new ExpectationException("No leaderboard row found for student '$studentname'", $this->getSession());
    }

    /**
     * Locate the per-question mark cell for a given student and 1-indexed
     * question number (i.e. the Nth ql-q cell in that student's row).
     *
     * @param string $studentname
     * @param int    $qnum
     * @return \Behat\Mink\Element\NodeElement
     */
    protected function find_leaderboard_question_cell(string $studentname, int $qnum) {
        $row = $this->find_leaderboard_row($studentname);
        $qcells = $row->findAll('css', 'td.ql-q');

        if (!isset($qcells[$qnum - 1])) {
            throw new ExpectationException(
                "Row for '$studentname' does not have a question $qnum cell",
                $this->getSession()
            );
        }

        return $qcells[$qnum - 1];
    }
}
