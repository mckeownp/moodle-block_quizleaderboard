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
 * Leaderboard data-fetching service.
 *
 * Fetches the FULL step history for every relevant question attempt and
 * processes it in PHP. This lets us:
 *   (a) correctly identify the most recent *graded* mark per question, even
 *       though adaptive mode appends a fresh 'todo' step after every graded
 *       submission (which previously caused zero-mark answers to look like
 *       "not attempted" when only the latest row was considered), and
 *   (b) replay the leaderboard as it stood at any point in time, by simply
 *       ignoring steps timestamped after the cut-off ("time travel" mode).
 *
 * @package    block_quizleaderboard
 * @copyright  2024 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_quizleaderboard;

/**
 * Service class for retrieving live (or historical) leaderboard data.
 */
class leaderboard_service {
    /**
     * question_attempt_steps states that represent a graded/answered submission,
     * as opposed to 'todo' (awaiting input) or other non-graded bookkeeping states.
     * Only a step in one of these states carries a meaningful mark.
     */
    const ANSWERED_STATES = [
        'complete',
        'invalid',
        'gradedright',
        'gradedwrong',
        'gradedpartial',
        'mangrright',
        'mangrwrong',
        'mangrpartial',
        'gave_up',
    ];

    /** @var \stdClass The quiz record. */
    private $quiz;

    /** @var bool Whether to show all students (teacher view). */
    private $canviewall;

    /**
     * Construct a leaderboard service for a given quiz.
     *
     * @param \stdClass $quiz       The quiz DB record.
     * @param bool      $canviewall True for teacher/manager view.
     */
    public function __construct(\stdClass $quiz, bool $canviewall) {
        $this->quiz       = $quiz;
        $this->canviewall = $canviewall;
    }

    /**
     * Look up a block_quizleaderboard instance's configuration directly from
     * the database, for a given quiz id.
     *
     * This deliberately avoids going through $PAGE->blocks (e.g.
     * get_blocks_for_region()), because that requires Moodle to have already
     * run its normal block-loading flow as part of rendering a page layout —
     * which has NOT happened in contexts like the AJAX endpoint or other
     * lightweight scripts, where calling it throws:
     * "block_manager has not yet loaded the blocks, it is too soon to
     * request the information you asked for."
     *
     * If multiple block instances are configured for the same quiz (e.g. one
     * per course page), the first one found is used; in practice they should
     * all share the same display preferences for a given quiz.
     *
     * @param int $quizid
     * @return \stdClass Config object with showstudentid/showpercentage/anonymise,
     *                    or an empty object if no matching block instance is found.
     */
    public static function get_block_config_for_quiz(int $quizid): \stdClass {
        global $DB;

        $records = $DB->get_records('block_instances', ['blockname' => 'quizleaderboard']);

        foreach ($records as $record) {
            if (empty($record->configdata)) {
                continue;
            }

            $config = @unserialize(base64_decode($record->configdata));
            if ($config !== false && !empty($config->quizid) && (int)$config->quizid === $quizid) {
                return (object)$config;
            }
        }

        return new \stdClass();
    }

    /**
     * Fetch and structure leaderboard data, optionally "as of" a point in time.
     *
     * @param int|null $asoftime Unix timestamp cut-off. Steps recorded after this
     *                           time are ignored, recreating the leaderboard as it
     *                           stood at that moment. Null = current/live state.
     * @return \stdClass
     */
    public function get_data(?int $asoftime = null): \stdClass {
        global $DB, $USER;

        $data = new \stdClass();

        // 1. Quiz slots in order. Description questions carry no mark and are
        // purely informational, so they are excluded here and never appear in
        // the leaderboard table at all.
        // Moodle 5.0+ always has question_references/question_versions tables.
        $sql = "SELECT qs.id, qs.slot, qs.maxmark
                  FROM {quiz_slots} qs
                  JOIN {question_references} qr
                    ON qr.itemid = qs.id
                   AND qr.component = 'mod_quiz'
                   AND qr.questionarea = 'slot'
                  JOIN {question_versions} qv
                    ON qv.questionbankentryid = qr.questionbankentryid
                   AND qv.version = (
                           SELECT MAX(qv2.version)
                             FROM {question_versions} qv2
                            WHERE qv2.questionbankentryid = qr.questionbankentryid
                       )
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qs.quizid = :quizid
                   AND q.qtype <> 'description'
              ORDER BY qs.slot ASC";

        $slots = $DB->get_records_sql($sql, ['quizid' => $this->quiz->id]);
        $data->questions = array_values($slots);

        // 2. Best attempt per student (by current sumgrades; "best" is a live-mode
        // concept only — for historical replay we still anchor on the same set of
        // attempts/students, we just filter which STEPS count further down).
        $statelist = ["'inprogress'", "'finished'", "'overdue'"];
        $statesql  = implode(',', $statelist);

        $userwhere  = '';
        $userparams = [];
        if (!$this->canviewall) {
            $userwhere  = ' AND qa.userid = :userid';
            $userparams = ['userid' => $USER->id];
        }

        $sql = "SELECT qa.id        AS attemptid,
                       qa.userid,
                       qa.sumgrades,
                       qa.timestart,
                       qa.timemodified,
                       qa.uniqueid,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename,
                       u.idnumber
                  FROM {quiz_attempts} qa
                  JOIN {user} u ON u.id = qa.userid
                 WHERE qa.quiz = :quizid
                   AND qa.state IN ($statesql)
                   AND qa.preview = 0
                       $userwhere
              ORDER BY qa.userid, qa.sumgrades DESC, qa.timemodified DESC";

        $attempts = $DB->get_records_sql($sql, array_merge(['quizid' => $this->quiz->id], $userparams));

        $bestattempt = [];
        foreach ($attempts as $attempt) {
            if (!isset($bestattempt[$attempt->userid])) {
                $bestattempt[$attempt->userid] = $attempt;
            }
        }

        if (empty($bestattempt)) {
            $data->rows = [];
            $data->earliest_time = null;
            $data->latest_time   = null;
            $data->first_question_activity = null;
            $data->last_question_activity  = null;
            return $data;
        }

        // 3. Fetch the ENTIRE step history (with timestamps) for every question
        // attempt belonging to these quiz attempts. We process this in PHP rather
        // than trying to express "latest graded step" purely in SQL, which proved
        // fragile across DB engines and adaptive-mode's todo/graded step interleaving.
        $attemptids = array_column($bestattempt, 'attemptid');
        [$idsql, $idparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED, 'atid');

        $sql = "SELECT qas.id                 AS stepid,
                       qas.questionattemptid,
                       qas.sequencenumber,
                       qas.state               AS stepstate,
                       qas.fraction,
                       qas.timecreated,
                       qa2.slot,
                       quiza2.id               AS attemptid
                  FROM {question_attempt_steps} qas
                  JOIN {question_attempts} qa2
                    ON qa2.id = qas.questionattemptid
                  JOIN {quiz_attempts} quiza2
                    ON quiza2.uniqueid = qa2.questionusageid
                 WHERE quiza2.id $idsql
              ORDER BY qas.questionattemptid ASC, qas.sequencenumber ASC";

        $steprows = $DB->get_records_sql($sql, $idparams);

        // Group steps by [attemptid][slot] => ordered list of steps.
        $stepsbyattemptslot = [];
        $firstquestionactivity = null;  // First GRADED step — for info panel.
        $lastquestionactivity  = null;  // Last GRADED step  — for info panel.
        $sliderearliest = null;         // Earliest ANY step — for slider range.
        $sliderlatest   = null;         // Latest   ANY step — for slider range.

        foreach ($steprows as $sr) {
            $stepsbyattemptslot[$sr->attemptid][$sr->slot][] = $sr;

            // Slider bounds: include ALL step timestamps (including initial 'todo'
            // steps) so the slider spans the entire session window even when all
            // graded submissions happen within the same clock second. This prevents
            // the slider collapsing to the 1-minute minimum in a fast quiz.
            if ($sr->timecreated > 0) {
                if ($sliderearliest === null || $sr->timecreated < $sliderearliest) {
                    $sliderearliest = $sr->timecreated;
                }
                if ($sliderlatest === null || $sr->timecreated > $sliderlatest) {
                    $sliderlatest = $sr->timecreated;
                }
            }

            // Info panel display: only graded/answered steps so that
            // "first/last attempt at a question" reflects real answering
            // activity, not bookkeeping steps like the initial 'todo'.
            if ($sr->timecreated > 0 && in_array($sr->stepstate, self::ANSWERED_STATES, true)) {
                if ($firstquestionactivity === null || $sr->timecreated < $firstquestionactivity) {
                    $firstquestionactivity = $sr->timecreated;
                }
                if ($lastquestionactivity === null || $sr->timecreated > $lastquestionactivity) {
                    $lastquestionactivity = $sr->timecreated;
                }
            }
        }

        // Also widen the slider range to include quiz attempt start times so a
        // teacher can see the leaderboard from the moment each student opened
        // the quiz. We deliberately exclude these from first/last_question_activity
        // (the info panel fields) since those should only reflect actual answering.
        foreach ($bestattempt as $attempt) {
            if (!empty($attempt->timestart) && $attempt->timestart > 0) {
                if ($sliderearliest === null || $attempt->timestart < $sliderearliest) {
                    $sliderearliest = $attempt->timestart;
                }
                if ($sliderlatest === null || $attempt->timestart > $sliderlatest) {
                    $sliderlatest = $attempt->timestart;
                }
            }
        }

        $data->earliest_time = $sliderearliest;
        $data->latest_time   = $sliderlatest;

        // Distinct: these reflect ONLY graded question activity for the info panel.
        $data->first_question_activity = $firstquestionactivity;
        $data->last_question_activity  = $lastquestionactivity;

        // Slot maxmark map.
        $slotmaxmark = [];
        foreach ($slots as $slot) {
            $slotmaxmark[$slot->slot] = (float)$slot->maxmark;
        }
        $totalmax = array_sum($slotmaxmark);

        // 4. Assemble rows, deriving each question's mark from the step history.
        $rows = [];
        foreach ($bestattempt as $attempt) {
            $row = new \stdClass();
            $row->userid    = $attempt->userid;
            $row->firstname = $attempt->firstname;
            $row->lastname  = $attempt->lastname;
            $row->idnumber  = $attempt->idnumber ?? '';

            $row->question_marks = [];
            $totalraw = 0.0;

            foreach ($slots as $slot) {
                $s = $slot->slot;

                $steps = $stepsbyattemptslot[$attempt->attemptid][$s] ?? [];
                $mark  = $this->derive_mark_from_steps($steps, $slotmaxmark[$s], $asoftime);

                $row->question_marks[$s] = $mark;
                if ($mark !== null) {
                    $totalraw += $mark;
                }
            }

            $row->total_raw  = $totalraw;
            $row->total_max  = $totalmax;
            $row->percentage = $totalmax > 0 ? round(($totalraw / $totalmax) * 100, 1) : 0;
            $rows[] = $row;
        }

        // Sort by total descending.
        usort($rows, fn($a, $b) => $b->total_raw <=> $a->total_raw);

        // Assign ranks (ties share rank).
        $prevtotal = null;
        $prevrank  = 0;
        $counter   = 0;
        foreach ($rows as &$row) {
            $counter++;
            if ($row->total_raw !== $prevtotal) {
                $prevrank  = $counter;
                $prevtotal = $row->total_raw;
            }
            $row->rank = $prevrank;
        }
        unset($row);

        $data->rows      = $rows;
        $data->total_max = $totalmax;
        $data->slots     = $slots;

        return $data;
    }

    /**
     * Walk an ordered list of question_attempt_steps for a single question and
     * derive the mark that should be displayed, optionally constrained to steps
     * recorded at or before $asoftime.
     *
     * The key fix here: we scan the FULL ordered history and remember the most
     * recent step whose state is a genuinely graded/answered state, instead of
     * naively trusting "the last row" (which in adaptive mode is very often a
     * trailing 'todo' step with no fraction, written immediately after grading
     * to let the student try again — this was previously causing zero-mark
     * answers to be misclassified as "not attempted").
     *
     * @param array    $steps    Ordered (by sequencenumber) steps for one question attempt.
     * @param float    $maxmark  The maximum mark for this question slot.
     * @param int|null $asoftime Optional cut-off timestamp for historical replay.
     * @return float|null Mark in raw points, or null if not yet answered (as of cut-off).
     */
    private function derive_mark_from_steps(array $steps, float $maxmark, ?int $asoftime): ?float {
        $lastgraded = null;

        foreach ($steps as $step) {
            // For historical replay, ignore any step recorded after the cut-off.
            if ($asoftime !== null && $step->timecreated > $asoftime) {
                break; // Steps are ordered, so nothing after this matters either.
            }

            if (in_array($step->stepstate, self::ANSWERED_STATES, true)) {
                $lastgraded = $step;
            }
        }

        if ($lastgraded === null) {
            return null; // No graded submission yet (at this point in time).
        }

        // Fraction is NULL when Moodle records a zero-score graded submission for
        // certain question behaviours/types — treat that as an explicit 0.0 rather
        // than "ungraded", since we already know the state is a graded state.
        return $lastgraded->fraction !== null
            ? (float)$lastgraded->fraction * $maxmark
            : 0.0;
    }
}
