@block @block_quizleaderboard
Feature: Quiz leaderboard includes randomly selected questions
  In order to run a leaderboard on quizzes built from question pools
  As a teacher
  I need random question slots to appear as columns, to have their marks shown
  against the right column, and to count towards the total and percentage,
  exactly like slots that hold a specific question

  # Regression coverage for: random slots were silently dropped from the
  # leaderboard. Only a slot holding a SPECIFIC question has a
  # question_references row; a random slot has a question_set_references row
  # instead. The slot query inner-joined question_references, so every random
  # slot vanished — no column, no contribution to the student's total, and no
  # contribution to the "Out of N" denominator either, which quietly inflated
  # everyone's percentage.
  #
  # Note the asymmetry in max marks below: the fixed questions are worth 5,
  # while Moodle always creates random slots worth 1. That is deliberate here —
  # it means a dropped random slot changes the "Out of N" total, so the totals
  # assertions catch the bug rather than merely the column count.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tina      | Teacher  | teacher1@example.com |
      | student1 | Alice     | Anderson | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
      | Course       | C1        | Pool A         |
      | Course       | C1        | Pool B         |
    And the following "questions" exist:
      | questioncategory | qtype       | name    | questiontext           | defaultmark |
      | Test questions   | truefalse   | Fixed1  | A fixed question       | 5           |
      | Test questions   | truefalse   | Fixed2  | Another fixed question | 5           |
      | Test questions   | description | Desc1   | A section header       | 0           |
      | Pool A           | truefalse   | PoolA-1 | Pool A question one    | 1           |
      | Pool A           | truefalse   | PoolA-2 | Pool A question two    | 1           |
      | Pool A           | truefalse   | PoolA-3 | Pool A question three  | 1           |
      | Pool B           | truefalse   | PoolB-1 | Pool B question one    | 1           |
    # Declared in their own table, without a defaultmark column: a random
    # question carries no mark of its own, and core always creates random slots
    # worth 1 regardless of what a maxmark column says.
    And the following "questions" exist:
      | questioncategory | qtype  | name            | questiontext |
      | Pool A           | random | Random (Pool A) | 0            |
      | Pool B           | random | Random (Pool B) | 0            |

  # -----------------------------------------------------------------------
  # Quiz made entirely of random questions
  # -----------------------------------------------------------------------

  Scenario: Every random slot gets its own leaderboard column
    Given the following "activities" exist:
      | activity | name      | course | idnumber | preferredbehaviour |
      | quiz     | Rand-only | C1     | qro      | adaptive           |
    And quiz "Rand-only" contains the following questions:
      | question        | page |
      | Random (Pool A) | 1    |
      | Random (Pool A) | 1    |
      | Random (Pool A) | 1    |
      | Random (Pool B) | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Rand-only" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Rand-only"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Rand-only"
    # Before the fix this table had NO question columns at all.
    Then the leaderboard table should have 4 question columns
    And the leaderboard column header 1 should show "Q1"
    And the leaderboard column header 2 should show "Q2"
    And the leaderboard column header 3 should show "Q3"
    And the leaderboard column header 4 should show "Q4"
    And I should see "Out of 4" in the ".ql-col-total" "css_element"

  Scenario: Marks on random slots appear against the correct column
    Given the following "activities" exist:
      | activity | name      | course | idnumber | preferredbehaviour |
      | quiz     | Rand-only | C1     | qro      | adaptive           |
    And quiz "Rand-only" contains the following questions:
      | question        | page |
      | Random (Pool A) | 1    |
      | Random (Pool A) | 1    |
      | Random (Pool A) | 1    |
      | Random (Pool B) | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Rand-only" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Rand-only"
    And the question in slot 1 is answered correctly by "student1" in quiz "Rand-only"
    And the question in slot 2 is answered incorrectly by "student1" in quiz "Rand-only"
    And the question in slot 4 is answered correctly by "student1" in quiz "Rand-only"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Rand-only"
    Then the leaderboard cell for "Alice Anderson" question 1 should show "1.00" with class "ql-full"
    And the leaderboard cell for "Alice Anderson" question 2 should show "0.00" with class "ql-zero"
    # Slot 3 was never answered, so it must stay a dash, not inherit a neighbour's mark.
    And the leaderboard cell for "Alice Anderson" question 3 should show "-" with class "ql-notdone"
    And the leaderboard cell for "Alice Anderson" question 4 should show "1.00" with class "ql-full"
    And the leaderboard total for "Alice Anderson" should be "2.00"
    And the leaderboard percentage for "Alice Anderson" should be "50%"

  Scenario: Slots sharing one pool are graded independently of each other
    Given the following "activities" exist:
      | activity | name      | course | idnumber | preferredbehaviour |
      | quiz     | Rand-pair | C1     | qrp      | adaptive           |
    And quiz "Rand-pair" contains the following questions:
      | question        | page |
      | Random (Pool A) | 1    |
      | Random (Pool A) | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Rand-pair" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Rand-pair"
    And the question in slot 2 is answered correctly by "student1" in quiz "Rand-pair"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Rand-pair"
    # Two slots drawing from the SAME pool must remain two distinct columns:
    # answering one must not mark the other.
    Then the leaderboard table should have 2 question columns
    And the leaderboard cell for "Alice Anderson" question 1 should show "-" with class "ql-notdone"
    And the leaderboard cell for "Alice Anderson" question 2 should show "1.00" with class "ql-full"
    And the leaderboard total for "Alice Anderson" should be "1.00"

  # -----------------------------------------------------------------------
  # Random questions mixed in with specific questions
  # -----------------------------------------------------------------------

  Scenario: A random slot between two fixed questions keeps the column order
    Given the following "activities" exist:
      | activity | name  | course | idnumber | preferredbehaviour |
      | quiz     | Mixed | C1     | qmx      | adaptive           |
    And quiz "Mixed" contains the following questions:
      | question        | page |
      | Fixed1          | 1    |
      | Random (Pool A) | 1    |
      | Fixed2          | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Mixed" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Mixed"
    And question "Fixed1" is answered correctly by "student1" in quiz "Mixed"
    And the question in slot 2 is answered correctly by "student1" in quiz "Mixed"
    And question "Fixed2" is answered incorrectly by "student1" in quiz "Mixed"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Mixed"
    Then the leaderboard table should have 3 question columns
    # Dropping the random slot would have shifted Fixed2's 0.00 into column 2.
    And the leaderboard cell for "Alice Anderson" question 1 should show "5.00" with class "ql-full"
    And the leaderboard cell for "Alice Anderson" question 2 should show "1.00" with class "ql-full"
    And the leaderboard cell for "Alice Anderson" question 3 should show "0.00" with class "ql-zero"
    And the leaderboard total for "Alice Anderson" should be "6.00"

  Scenario: A random slot's max mark counts towards the total and percentage
    Given the following "activities" exist:
      | activity | name  | course | idnumber | preferredbehaviour |
      | quiz     | Mixed | C1     | qmx      | adaptive           |
    And quiz "Mixed" contains the following questions:
      | question        | page |
      | Fixed1          | 1    |
      | Random (Pool A) | 1    |
      | Fixed2          | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Mixed" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Mixed"
    And question "Fixed1" is answered correctly by "student1" in quiz "Mixed"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Mixed"
    # 5 + 1 + 5. With the random slot dropped this read "Out of 10" and 50%,
    # overstating every student's percentage.
    Then I should see "Out of 11" in the ".ql-col-total" "css_element"
    And the leaderboard total for "Alice Anderson" should be "5.00"
    And the leaderboard percentage for "Alice Anderson" should be "45.5%"

  # -----------------------------------------------------------------------
  # Random questions alongside descriptions
  # -----------------------------------------------------------------------

  Scenario: Descriptions stay excluded when random slots are present
    Given the following "activities" exist:
      | activity | name      | course | idnumber | preferredbehaviour |
      | quiz     | Desc-rand | C1     | qdr      | adaptive           |
    And quiz "Desc-rand" contains the following questions:
      | question        | page |
      | Desc1           | 1    |
      | Random (Pool A) | 1    |
      | Fixed1          | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Desc-rand" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Desc-rand"
    # Quiz slot 2 is the random question. The description occupies quiz slot 1
    # but no leaderboard column, so the random slot is leaderboard column Q1 —
    # slot numbers and column numbers deliberately diverge here.
    And the question in slot 2 is answered correctly by "student1" in quiz "Desc-rand"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Desc-rand"
    # The two rules have to hold at once: keep slots with no specific question
    # (random), still drop descriptions. Widening the query far enough to let
    # random slots through must not let the description through with them.
    Then the leaderboard table should have 2 question columns
    And the leaderboard column header 1 should show "Q1"
    And the leaderboard column header 2 should show "Q2"
    And I should see "Out of 6" in the ".ql-col-total" "css_element"
    And the leaderboard cell for "Alice Anderson" question 1 should show "1.00" with class "ql-full"
    And the leaderboard cell for "Alice Anderson" question 2 should show "-" with class "ql-notdone"
