@block @block_quizleaderboard
Feature: Quiz leaderboard display and colour coding
  In order to monitor student progress on an open quiz
  As a teacher
  I need to see a live leaderboard with correctly colour-coded marks

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | teacher1 | Tina      | Teacher  | teacher1@example.com | T0001    |
      | student1 | Alice     | Anderson | student1@example.com | S0001    |
      | student2 | Bob       | Brown    | student2@example.com | S0002    |
      | student3 | Carol     | Clarke   | student3@example.com | S0003    |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
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
      | Test questions   | truefalse | Q3   | Third question is true  | 10          |
    And quiz "Test Quiz" contains the following questions:
      | question | page |
      | Q1       | 1    |
      | Q2       | 1    |
      | Q3       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Test Quiz" in course "C1"

  Scenario: Teacher sees the leaderboard block on the quiz page
    Given user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I am on the "Test Quiz" "mod_quiz > View" page logged in as "teacher1"
    Then I should see "Quiz Leaderboard"
    And I should see "Alice Anderson"

  Scenario: Student does not see the leaderboard block at all
    Given user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "student1"
    And I am on the "Test Quiz" "mod_quiz > View" page logged in as "student1"
    Then I should not see "Quiz Leaderboard"

  Scenario: Full marks shown in green on the full leaderboard page
    Given user "student1" has begun a leaderboard attempt at quiz "Test Quiz" with responses:
      | slot | response |
      | 1    | True     |
    And question "Q1" is answered correctly by "student1" in quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then the leaderboard cell for "Alice Anderson" question 1 should show "5.00" with class "ql-full"

  Scenario: Zero marks shown in red, not as "not attempted"
    Given user "student1" has begun a leaderboard attempt at quiz "Test Quiz" with responses:
      | slot | response |
      | 1    | False    |
    And question "Q1" is answered incorrectly by "student1" in quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then the leaderboard cell for "Alice Anderson" question 1 should show "0.00" with class "ql-zero"
    And the leaderboard cell for "Alice Anderson" question 1 should not have class "ql-notdone"

  Scenario: Unattempted question shown as a dash
    Given user "student1" has begun a leaderboard attempt at quiz "Test Quiz" with responses:
      | slot | response |
      | 1    | True     |
    And question "Q1" is answered correctly by "student1" in quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then the leaderboard cell for "Alice Anderson" question 2 should show "-" with class "ql-notdone"
    And the leaderboard cell for "Alice Anderson" question 3 should show "-" with class "ql-notdone"

  Scenario: Mixed marks across multiple students show correct colours for each
    Given user "student1" has begun a leaderboard attempt at quiz "Test Quiz" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
    And question "Q1" is answered correctly by "student1" in quiz "Test Quiz"
    And question "Q2" is answered incorrectly by "student1" in quiz "Test Quiz"
    And user "student2" has begun a leaderboard attempt at quiz "Test Quiz" with responses:
      | slot | response |
      | 1    | False    |
    And question "Q1" is answered incorrectly by "student2" in quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then the leaderboard cell for "Alice Anderson" question 1 should show "5.00" with class "ql-full"
    And the leaderboard cell for "Alice Anderson" question 2 should show "0.00" with class "ql-zero"
    And the leaderboard cell for "Alice Anderson" question 3 should show "-" with class "ql-notdone"
    And the leaderboard cell for "Bob Brown" question 1 should show "0.00" with class "ql-zero"

  Scenario: Total mark and percentage update as questions are answered
    Given user "student1" has begun a leaderboard attempt at quiz "Test Quiz" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | True     |
    And question "Q1" is answered correctly by "student1" in quiz "Test Quiz"
    And question "Q2" is answered correctly by "student1" in quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then the leaderboard total for "Alice Anderson" should be "10.00"
    And the leaderboard percentage for "Alice Anderson" should be "50%"

  Scenario: Total column header shows "Out of" the quiz maximum
    Given user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then I should see "Out of 20" in the ".ql-col-total" "css_element"

  Scenario: Student ID column is shown on the full leaderboard page
    Given user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"
    Then I should see "S0001"

  Scenario: Deferred feedback quiz shows an info note but still renders the leaderboard
    Given the following "activities" exist:
      | activity | name           | course | idnumber | preferredbehaviour |
      | quiz     | Deferred Quiz  | C1     | quiz2    | deferredfeedback    |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext             | defaultmark |
      | Test questions   | truefalse | DQ1  | Deferred question is true | 5          |
    And quiz "Deferred Quiz" contains the following questions:
      | question | page |
      | DQ1      | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Deferred Quiz" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Deferred Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Deferred Quiz"
    Then I should see "deferred feedback"
    And I should see "Alice Anderson"

  Scenario: Deferred feedback quiz shows dashes until quiz is submitted and graded
    Given the following "activities" exist:
      | activity | name           | course | idnumber | preferredbehaviour |
      | quiz     | Deferred Quiz  | C1     | quiz2    | deferredfeedback    |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext             | defaultmark |
      | Test questions   | truefalse | DQ1  | Deferred question is true | 5          |
    And quiz "Deferred Quiz" contains the following questions:
      | question | page |
      | DQ1      | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Deferred Quiz" in course "C1"
    And user "student1" has begun a leaderboard attempt at quiz "Deferred Quiz"
    When I log in as "teacher1"
    And I view the full leaderboard for quiz "Deferred Quiz"
    Then the leaderboard cell for "Alice Anderson" question 1 should show "-" with class "ql-notdone"
