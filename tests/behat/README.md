# block_quizleaderboard — Behat test suite

This directory contains an extensive Behat suite covering the leaderboard's
display logic, colour coding, sorting, sidebar summary, and time-travel mode.
It exists so the plugin can be regression-tested automatically instead of by
hand-building students, quizzes, and attempts through the UI every time.

## What's covered

- `leaderboard_display.feature` — block visibility (teacher-only), per-question
  colour coding (green/orange/red/dash), totals, percentages, the "Out of N"
  header, the student ID column, and the non-adaptive-quiz warning. This
  includes explicit regression coverage for the "zero marks must show as red,
  not as a dash" bug.
- `leaderboard_sorting.feature` — clicking any column header toggles
  ascending/descending sort, the sort indicator (`aria-sort`) updates
  correctly, switching columns resets the previous indicator, and keyboard
  activation (Enter) works as well as a mouse click.
- `leaderboard_sidebar_summary.feature` — the compact sidebar block, the
  "View full leaderboard" link position, and the 20-row cap with its
  "Showing top N of M" note.
- `leaderboard_timetravel.feature` — the time-travel toggle and slider:
  default-off behaviour, revealing/hiding the slider, replaying marks at
  different points in time (including the zero-marks-at-a-past-time case),
  reverting to live mode, and sorting while in historical mode.

## How it works

A custom Behat context, `behat_block_quizleaderboard.php`, provides step
definitions that create adaptive-mode quiz attempts and submit graded
responses directly via Moodle's quiz attempt and question engine APIs
(`quiz_create_attempt`, `quiz_start_new_attempt`,
`quiz_attempt::process_submitted_actions`), rather than clicking through the
actual quiz-taking UI. This keeps the suite fast (no per-question page loads)
while still exercising the exact same `quiz_attempts` /
`question_attempt_steps` data that the leaderboard's data layer reads from.

For time-travel scenarios, steps like:

```gherkin
Given user "student1" started quiz "Test Quiz" at "-60 minutes"
And question "Q1" was answered correctly by "student1" in quiz "Test Quiz" at "-30 minutes"
```

accept any `strtotime()`-compatible relative time expression, and the context
backdates the relevant `quiz_attempts.timestart` / `question_attempt_steps.timecreated`
rows accordingly, so the slider has real historical spread to replay.

## Running the suite

From your Moodle root, with Behat already initialised (`php admin/tool/behat/cli/init.php`):

```bash
# Run everything for this plugin
php admin/tool/behat/cli/run.php --tags=@block_quizleaderboard

# Run just one feature
php admin/tool/behat/cli/run.php blocks/quizleaderboard/tests/behat/leaderboard_display.feature

# Run only the JS-dependent suites (sorting, time-travel) with a real browser driver configured
php admin/tool/behat/cli/run.php --tags="@block_quizleaderboard&&@javascript"
```

## Notes / assumptions

- `quiz_settings` and `quiz_attempt` are namespaced as `mod_quiz\quiz_settings`
  and `mod_quiz\quiz_attempt` on modern Moodle (the mod_quiz namespace
  migration), not global-namespace classes. Since
  `behat_block_quizleaderboard` is itself declared in the global namespace
  (as Moodle requires for Behat context classes), referring to `quiz_settings`
  or `quiz_attempt` unqualified would otherwise resolve to a non-existent
  global class and fail with "Class quiz_settings not found" — the context
  class imports both via `use mod_quiz\quiz_settings;` / `use mod_quiz\quiz_attempt;`
  at the top of the file so the short names resolve correctly everywhere below.
- Steps that create or progress quiz attempts are named
  `user "X" has begun a leaderboard attempt at quiz "Y"` (not the more natural
  "has started an attempt at quiz"), because Moodle core's own `behat_mod_quiz`
  context already defines a step matching that exact phrasing. Reusing it
  produces an "Ambiguous match" Behat error rather than a clean override, since
  Behat has no way to know which of the two matching definitions you intended.
- Quiz attempt creation/answering steps temporarily switch the global `$USER`
  (via `\core\session\manager::set_user()`) to the target student for the
  duration of the underlying `quiz_create_attempt()` / `process_submitted_actions()`
  calls, then restore whatever user (or no user) was active beforehand. This
  is necessary because those Moodle APIs are written to run inside a real
  logged-in request for the acting student, but Behat step code runs in the
  test process rather than inside the Mink-driven browser session — without
  the explicit switch/restore, subsequent navigation steps in the same
  scenario can end up on an unauthenticated session ("You are not logged in").


- The custom steps currently target **true/false** questions specifically
  (matching the `qtype: truefalse` fixtures in the Background sections),
  since that's the simplest way to deterministically produce "correct" vs
  "incorrect" graded outcomes. If you extend the suite to other question
  types, `submit_response()` in the context class will need a
  question-type-aware response array instead of the hardcoded `answer => 1/0`.
- `i_set_the_slider_to_minute()` sets the `<input type="range">` value via
  JavaScript and dispatches an `input` event, which is what the
  `timetravel.js` AMD module listens for — this is more reliable across
  browser drivers than simulating a literal drag gesture.
- The slider's AJAX refresh is debounced by 250ms client-side; the step
  definition waits 600ms after dispatching the input event to comfortably
  clear that debounce before assertions run.
