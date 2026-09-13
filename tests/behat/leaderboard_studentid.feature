@block @block_quizleaderboard
Feature: Quiz leaderboard student ID number column
  In order to display a leaderboard publicly without identifying students
  As a teacher
  I need the student ID number column to obey its setting, to appear only on the
  full leaderboard page, and to be suppressed whenever names are anonymised

  # The "Show student ID number" setting was previously read into a variable and
  # then never used: the full page showed the ID column unconditionally, and the
  # compact block never showed it at all. Worse, because the column ignored the
  # setting it also ignored anonymising — an anonymised board rendered rows of
  # "Student 1 | S0001", which identifies the student just as surely as a name.
  #
  # Note the column holds the user's idnumber PROFILE FIELD (the institutional
  # student ID), never the internal user.id database key.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | teacher1 | Tina      | Teacher  | teacher1@example.com | T0001    |
      | student1 | Alice     | Anderson | student1@example.com | S0001    |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name      | course | idnumber | preferredbehaviour |
      | quiz     | Test Quiz | C1     | quiz1    | adaptive           |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext           | defaultmark |
      | Test questions   | truefalse | Q1   | First question is true | 5           |
    And quiz "Test Quiz" contains the following questions:
      | question | page |
      | Q1       | 1    |

  # -----------------------------------------------------------------------
  # The setting is actually honoured
  # -----------------------------------------------------------------------

  Scenario: ID column is shown on the full page when the setting is enabled
    Given the block_quizleaderboard plugin is added with quizid for "Test Quiz" in course "C1" with config:
      | showstudentid | 1 |
      | anonymise     | 0 |
    And user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then the leaderboard table should show the student ID column
    And I should see "S0001"
    And I should see "Alice Anderson"

  Scenario: ID column is hidden on the full page when the setting is disabled
    Given the block_quizleaderboard plugin is added with quizid for "Test Quiz" in course "C1" with config:
      | showstudentid | 0 |
      | anonymise     | 0 |
    And user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    # Previously the column appeared regardless of this setting.
    Then the leaderboard table should not show the student ID column
    And I should not see "S0001"
    # The name is still shown: only the ID column is suppressed here.
    And I should see "Alice Anderson"

  # -----------------------------------------------------------------------
  # Anonymising overrides the setting
  # -----------------------------------------------------------------------

  Scenario: Anonymising hides the ID column even when the setting is enabled
    Given the block_quizleaderboard plugin is added with quizid for "Test Quiz" in course "C1" with config:
      | showstudentid | 1 |
      | anonymise     | 1 |
    And user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    # The whole point of the fix: an anonymised board must not carry anything
    # that identifies the student, and an ID number identifies them.
    Then the leaderboard table should not show the student ID column
    And I should not see "S0001"
    And I should not see "Alice Anderson"
    And I should see "Student 1"

  Scenario: Anonymising with the setting disabled also hides the ID column
    Given the block_quizleaderboard plugin is added with quizid for "Test Quiz" in course "C1" with config:
      | showstudentid | 0 |
      | anonymise     | 1 |
    And user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then the leaderboard table should not show the student ID column
    And I should not see "S0001"
    And I should see "Student 1"

  # -----------------------------------------------------------------------
  # The compact sidebar block never shows the column
  # -----------------------------------------------------------------------

  Scenario: Compact sidebar block has no ID column even when the setting is enabled
    Given the block_quizleaderboard plugin is added with quizid for "Test Quiz" in course "C1" with config:
      | showstudentid | 1 |
      | anonymise     | 0 |
    And user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I am on the "Test Quiz" "mod_quiz > View" page logged in as "teacher1"
    # By design: there is no room for it in the sidebar, which is why the
    # setting's label says "(in full page mode)".
    Then the leaderboard table should not show the student ID column
    And I should not see "S0001" in the "block_quizleaderboard" "block"
    And I should see "Alice Anderson" in the "block_quizleaderboard" "block"

  Scenario: Marks and totals are unaffected by hiding the ID column
    Given the block_quizleaderboard plugin is added with quizid for "Test Quiz" in course "C1" with config:
      | showstudentid | 0 |
      | anonymise     | 0 |
    And user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    And question "Q1" is answered correctly by "student1" in quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    # Dropping a column shifts every later cell along, so confirm the marks,
    # total and percentage still land where they belong.
    Then the leaderboard table should not show the student ID column
    And the leaderboard cell for "Alice Anderson" question 1 should show "5.00" with class "ql-full"
    And the leaderboard total for "Alice Anderson" should be "5.00"
    And the leaderboard percentage for "Alice Anderson" should be "100%"
