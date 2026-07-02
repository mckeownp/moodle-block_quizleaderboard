@block @block_quizleaderboard @javascript
Feature: Quiz leaderboard time-travel mode
  In order to understand how a quiz attempt unfolded over time
  As a teacher
  I need to replay the leaderboard as it stood at any point in time

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Tina      | Teacher  | teacher1@example.com  |
      | student1 | Alice     | Anderson | student1@example.com  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name      | course | idnumber | preferredbehaviour |
      | quiz     | Test Quiz | C1     | quiz1    | adaptive            |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext            | defaultmark |
      | Test questions   | truefalse | Q1   | First question is true  | 5           |
      | Test questions   | truefalse | Q2   | Second question is true | 5           |
    And quiz "Test Quiz" contains the following questions:
      | question | page |
      | Q1       | 1    |
      | Q2       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Test Quiz" in course "C1"

  Scenario: Time-travel toggle is off by default and the live leaderboard is shown
    Given user "student1" started quiz "Test Quiz" at "-60 minutes"
    And question "Q1" was answered correctly by "student1" in quiz "Test Quiz" at "-40 minutes"
    And question "Q2" was answered incorrectly by "student1" in quiz "Test Quiz" at "-20 minutes"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then the "Time-travel mode" "checkbox" should not be checked
    And the leaderboard slider should be disabled
    And the leaderboard cell for "Alice Anderson" question 1 should show "5.00" with class "ql-full"

  Scenario: Enabling time-travel mode reveals the slider
    Given user "student1" started quiz "Test Quiz" at "-60 minutes"
    And question "Q1" was answered correctly by "student1" in quiz "Test Quiz" at "-40 minutes"
    And question "Q2" was answered incorrectly by "student1" in quiz "Test Quiz" at "-20 minutes"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    And I enable time-travel mode
    Then the leaderboard slider should be enabled

  Scenario: Moving the slider to before any answers were submitted shows all dashes
    Given user "student1" started quiz "Test Quiz" at "-60 minutes"
    And question "Q1" was answered correctly by "student1" in quiz "Test Quiz" at "-30 minutes"
    And question "Q2" was answered incorrectly by "student1" in quiz "Test Quiz" at "-10 minutes"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    And I enable time-travel mode
    And I set the leaderboard time-travel slider to minute 5
    Then the leaderboard cell for "Alice Anderson" question 1 should show "-" with class "ql-notdone"
    And the leaderboard cell for "Alice Anderson" question 2 should show "-" with class "ql-notdone"

  Scenario: Moving the slider to between two answers shows only the earlier one
    Given user "student1" started quiz "Test Quiz" at "-60 minutes"
    And question "Q1" was answered correctly by "student1" in quiz "Test Quiz" at "-30 minutes"
    And question "Q2" was answered incorrectly by "student1" in quiz "Test Quiz" at "-10 minutes"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    And I enable time-travel mode
    And I set the leaderboard time-travel slider to minute 40
    Then the leaderboard cell for "Alice Anderson" question 1 should show "5.00" with class "ql-full"
    And the leaderboard cell for "Alice Anderson" question 2 should show "-" with class "ql-notdone"

  Scenario: Moving the slider to after both answers shows both marks, including zero
    Given user "student1" started quiz "Test Quiz" at "-60 minutes"
    And question "Q1" was answered correctly by "student1" in quiz "Test Quiz" at "-30 minutes"
    And question "Q2" was answered incorrectly by "student1" in quiz "Test Quiz" at "-10 minutes"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    And I enable time-travel mode
    And I set the leaderboard time-travel slider to minute 60
    Then the leaderboard cell for "Alice Anderson" question 1 should show "5.00" with class "ql-full"
    And the leaderboard cell for "Alice Anderson" question 2 should show "0.00" with class "ql-zero"

  Scenario: Disabling time-travel mode reverts to the live leaderboard
    Given user "student1" started quiz "Test Quiz" at "-60 minutes"
    And question "Q1" was answered correctly by "student1" in quiz "Test Quiz" at "-30 minutes"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    And I enable time-travel mode
    And I set the leaderboard time-travel slider to minute 5
    Then the leaderboard cell for "Alice Anderson" question 1 should show "-" with class "ql-notdone"
    When I disable time-travel mode
    Then the leaderboard cell for "Alice Anderson" question 1 should show "5.00" with class "ql-full"

  Scenario: Viewing label updates to show the selected point in time, not "last updated now"
    Given user "student1" started quiz "Test Quiz" at "-60 minutes"
    And question "Q1" was answered correctly by "student1" in quiz "Test Quiz" at "-30 minutes"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    And I enable time-travel mode
    And I set the leaderboard time-travel slider to minute 5
    Then I should see "Viewing leaderboard as of"
    And I should not see "Last updated:"

  Scenario: Time-travel toggle is disabled with an explanatory note when there is no attempt history
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then I should see "Not enough attempt history yet to enable time-travel mode."

  Scenario: Sorting still works correctly while viewing a historical point in time
    Given user "student1" started quiz "Test Quiz" at "-60 minutes"
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student2 | Bob       | Brown    | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student2 | C1     | student |
    And user "student2" started quiz "Test Quiz" at "-60 minutes"
    And question "Q1" was answered correctly by "student1" in quiz "Test Quiz" at "-50 minutes"
    And question "Q1" was answered incorrectly by "student2" in quiz "Test Quiz" at "-45 minutes"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    And I enable time-travel mode
    And I set the leaderboard time-travel slider to minute 55
    And I click on the "Total" leaderboard column header
    Then row 1 of the leaderboard table should contain "Bob Brown"
    And row 2 of the leaderboard table should contain "Alice Anderson"
