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
- `leaderboard_description_questions.feature` — description questions are
  excluded entirely, wherever they sit in the quiz, without disturbing question
  numbering, totals or percentages.
- `leaderboard_random_questions.feature` — randomly selected questions appear as
  columns, are graded against the right column, and count towards both the
  student's total and the "Out of N" denominator. This is regression coverage
  for a bug where every random slot was silently dropped from the leaderboard:
  only a slot holding a *specific* question has a `question_references` row, so
  the slot query's inner join to that table discarded random slots — losing the
  column, the marks, and the max-mark contribution (which quietly inflated
  everyone's percentage). One scenario deliberately mixes a random slot with a
  description to pin down both rules at once.

## How it works

A custom Behat context, `behat_block_quizleaderboard.php`, provides step
definitions that create adaptive-mode quiz attempts and submit graded responses
by **writing the underlying rows directly** (`question_usages`,
`question_attempts`, `question_attempt_steps`, `quiz_attempts`), rather than
clicking through the actual quiz-taking UI. This keeps the suite fast (no
per-question page loads) while still exercising the exact same `quiz_attempts` /
`question_attempt_steps` data that the leaderboard's data layer reads from.

Note this deliberately does *not* go through `quiz_create_attempt()` /
`quiz_attempt::process_submitted_actions()`: those APIs are written to run
inside a real logged-in request and touch `$_SESSION`, which is the same PHP
session Mink's browser is authenticated against — using them left subsequent
navigation steps on a "You are not logged in" page. See the comments on
`start_attempt()` and `as_user()` for the full story.

### Random questions

Seeding attempts by hand means the context has to do a job the quiz normally
does for itself: decide which question a **random** slot serves. A random slot
has no `question_references` row — it has a `question_set_references` row whose
`filtercondition` names the pool — so `get_question_id_for_slot()` falls back to
`draw_question_from_pool()`.

That draw is deliberately **deterministic**: the first not-yet-drawn question in
the pool, in question bank entry order (i.e. the order questions are declared in
the feature file). Real quizzes draw at random, but a scenario has to be able to
say "the question in slot 2 is answered correctly" and get the same result every
run. Slots sharing a pool draw *different* questions, as core does, so a pool
needs at least as many questions as the slots pointing at it.

Because the drawn question's name isn't knowable up front, random slots are
answered by position:

```gherkin
And the question in slot 2 is answered correctly by "student1" in quiz "Rand-only"
```

Two things to watch when writing these scenarios:

- Moodle always creates random slots worth **1 mark**, whatever a `maxmark`
  column says — `structure::add_random_questions()` hard-codes it. Pairing
  random slots with higher-valued fixed questions is useful: it makes the
  "Out of N" assertions sensitive to a dropped random slot.
- Quiz **slot** numbers and leaderboard **column** numbers are not the same
  thing once a description is in the quiz, since descriptions take a slot but no
  column. The `Desc-rand` scenario relies on exactly that.

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
- Where a step does need to act as a particular user, `as_user()` swaps the
  global `$USER` directly and restores it in a `finally` block. It deliberately
  avoids `\core\session\manager::set_user()`, which manipulates `$_SESSION` —
  the same PHP session Mink's browser is authenticated against — and so left
  subsequent navigation steps on "You are not logged in".
- Adding a **new feature file** means regenerating the Behat config before it
  will run; `run.php` works from the generated `behat.yml`, not from whatever is
  on disk. `php admin/tool/behat/cli/util.php --enable` is enough, or
  `php admin/tool/behat/cli/init.php` if it reports a version mismatch.
- The custom steps currently target **true/false** questions specifically
  (matching the `qtype: truefalse` fixtures in the Background sections),
  since that's the simplest way to deterministically produce "correct" vs
  "incorrect" graded outcomes — `grade_slot()` looks the question's right/wrong
  answer ids straight out of `question_answers`. This applies to the pools that
  random slots draw from as well, so keep those true/false too. If you extend
  the suite to other question types, `grade_slot()` will need a
  question-type-aware response array instead of the hardcoded `:answer` value.
- `i_set_the_slider_to_minute()` sets the `<input type="range">` value via
  JavaScript and dispatches an `input` event, which is what the
  `timetravel.js` AMD module listens for — this is more reliable across
  browser drivers than simulating a literal drag gesture.
- The slider's AJAX refresh is debounced by 250ms client-side; the step
  definition waits 600ms after dispatching the input event to comfortably
  clear that debounce before assertions run.
