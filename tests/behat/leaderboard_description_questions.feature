@block @block_quizleaderboard
Feature: Quiz leaderboard excludes description questions
  In order to see an uncluttered leaderboard for quizzes that contain descriptions
  As a teacher
  I need description questions to be completely excluded from the leaderboard
  table, with real questions numbered sequentially and totals/percentages
  unaffected, regardless of where the descriptions sit in the quiz

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
    And the following "questions" exist:
      | questioncategory | qtype       | name | questiontext                    | defaultmark |
      | Test questions   | description | D1   | This is a section header        | 0           |
      | Test questions   | truefalse   | Q1   | First scored question is true   | 5           |
      | Test questions   | description | D2   | This is a mid-quiz description  | 0           |
      | Test questions   | truefalse   | Q2   | Second scored question is true  | 5           |
      | Test questions   | description | D3   | This is a closing note          | 0           |

  # -----------------------------------------------------------------------
  # Quiz with description at the START then two real questions
  # -----------------------------------------------------------------------

  Scenario: Description at the start adds no column and Q1/Q2 are numbered from 1
    Given the following "activities" exist:
      | activity | name          | course | idnumber | preferredbehaviour |
      | quiz     | Desc-first    | C1     | qdf      | adaptive            |
    And quiz "Desc-first" contains the following questions:
      | question | page |
      | D1       | 1    |
      | Q1       | 1    |
      | Q2       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Desc-first" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Desc-first"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Desc-first"
    Then the leaderboard table should have 2 question columns
    And the leaderboard column header 1 should show "Q1"
    And the leaderboard column header 2 should show "Q2"

  Scenario: Description at the start adds no cell to a student's row
    Given the following "activities" exist:
      | activity | name          | course | idnumber | preferredbehaviour |
      | quiz     | Desc-first    | C1     | qdf      | adaptive            |
    And quiz "Desc-first" contains the following questions:
      | question | page |
      | D1       | 1    |
      | Q1       | 1    |
      | Q2       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Desc-first" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Desc-first"
    And question "Q1" is answered correctly by "student1" in quiz "Desc-first"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Desc-first"
    Then the leaderboard cell for "Alice Anderson" question 1 should show "5.00" with class "ql-full"

  Scenario: Description does not count towards the total mark or percentage
    Given the following "activities" exist:
      | activity | name          | course | idnumber | preferredbehaviour |
      | quiz     | Desc-first    | C1     | qdf      | adaptive            |
    And quiz "Desc-first" contains the following questions:
      | question | page |
      | D1       | 1    |
      | Q1       | 1    |
      | Q2       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Desc-first" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Desc-first"
    And question "Q1" is answered correctly by "student1" in quiz "Desc-first"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Desc-first"
    # Total max should be 10 (Q1=5 + Q2=5), not 10 + 0 from description.
    Then I should see "Out of 10" in the ".ql-col-total" "css_element"
    And the leaderboard total for "Alice Anderson" should be "5.00"
    And the leaderboard percentage for "Alice Anderson" should be "50%"

  # -----------------------------------------------------------------------
  # Quiz with description in the MIDDLE
  # -----------------------------------------------------------------------

  Scenario: Description in the middle adds no column and does not break numbering
    Given the following "activities" exist:
      | activity | name          | course | idnumber | preferredbehaviour |
      | quiz     | Desc-mid      | C1     | qdm      | adaptive            |
    And quiz "Desc-mid" contains the following questions:
      | question | page |
      | Q1       | 1    |
      | D1       | 1    |
      | Q2       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Desc-mid" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Desc-mid"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Desc-mid"
    Then the leaderboard table should have 2 question columns
    And the leaderboard column header 1 should show "Q1"
    And the leaderboard column header 2 should show "Q2"

  # -----------------------------------------------------------------------
  # Quiz with description at the END
  # -----------------------------------------------------------------------

  Scenario: Description at the end adds no trailing column
    Given the following "activities" exist:
      | activity | name          | course | idnumber | preferredbehaviour |
      | quiz     | Desc-end      | C1     | qde      | adaptive            |
    And quiz "Desc-end" contains the following questions:
      | question | page |
      | Q1       | 1    |
      | Q2       | 1    |
      | D1       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Desc-end" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Desc-end"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Desc-end"
    Then the leaderboard table should have 2 question columns
    And the leaderboard column header 1 should show "Q1"
    And the leaderboard column header 2 should show "Q2"

  # -----------------------------------------------------------------------
  # Quiz with MULTIPLE descriptions interspersed
  # -----------------------------------------------------------------------

  Scenario: Multiple descriptions are all excluded, leaving only real questions numbered sequentially
    Given the following "activities" exist:
      | activity | name          | course | idnumber | preferredbehaviour |
      | quiz     | Desc-multi    | C1     | qdm2     | adaptive            |
    And quiz "Desc-multi" contains the following questions:
      | question | page |
      | D1       | 1    |
      | Q1       | 1    |
      | D2       | 1    |
      | Q2       | 1    |
      | D3       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Desc-multi" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Desc-multi"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Desc-multi"
    Then the leaderboard table should have 2 question columns
    And the leaderboard column header 1 should show "Q1"
    And the leaderboard column header 2 should show "Q2"

  Scenario: Scored questions between descriptions have correct marks
    Given the following "activities" exist:
      | activity | name          | course | idnumber | preferredbehaviour |
      | quiz     | Desc-multi    | C1     | qdm2     | adaptive            |
    And quiz "Desc-multi" contains the following questions:
      | question | page |
      | D1       | 1    |
      | Q1       | 1    |
      | D2       | 1    |
      | Q2       | 1    |
      | D3       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Desc-multi" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Desc-multi"
    And question "Q1" is answered correctly by "student1" in quiz "Desc-multi"
    And question "Q2" is answered incorrectly by "student1" in quiz "Desc-multi"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Desc-multi"
    Then the leaderboard cell for "Alice Anderson" question 1 should show "5.00" with class "ql-full"
    And the leaderboard cell for "Alice Anderson" question 2 should show "0.00" with class "ql-zero"
    And the leaderboard total for "Alice Anderson" should be "5.00"
    And the leaderboard percentage for "Alice Anderson" should be "50%"
