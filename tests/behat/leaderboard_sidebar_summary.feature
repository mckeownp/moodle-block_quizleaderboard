@block @block_quizleaderboard
Feature: Quiz leaderboard sidebar summary
  In order to monitor a quiz without leaving the activity page
  As a teacher
  I need a compact leaderboard summary in the sidebar with a link to full detail

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tina      | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name      | course | idnumber | preferredbehaviour |
      | quiz     | Test Quiz | C1     | quiz1    | adaptive            |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext | defaultmark |
      | Test questions   | truefalse | Q1   | Is it true?  | 5           |
    And quiz "Test Quiz" contains the following questions:
      | question | page |
      | Q1       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Test Quiz" in course "C1"

  Scenario: Sidebar block shows a "View full leaderboard" link at the top
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Anderson | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I am on the "Test Quiz" "mod_quiz > View" page logged in as "teacher1"
    Then I should see "View full leaderboard" in the "block_quizleaderboard" "block"
    And the "View full leaderboard" link should appear before "Alice Anderson" in the "block_quizleaderboard" "block"

  Scenario: Sidebar block summary shows at most 20 students
    Given 25 students have begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I am on the "Test Quiz" "mod_quiz > View" page logged in as "teacher1"
    Then I should see "Showing top 20 of 25 students" in the "block_quizleaderboard" "block"
    And the number of rows in the leaderboard table in the "block_quizleaderboard" "block" should be 20

  Scenario: Sidebar block does not show the truncation note when 20 or fewer students
    Given 15 students have begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I am on the "Test Quiz" "mod_quiz > View" page logged in as "teacher1"
    Then I should not see "Showing top" in the "block_quizleaderboard" "block"
    And the number of rows in the leaderboard table in the "block_quizleaderboard" "block" should be 15

  Scenario: Sidebar block does not show per-question columns, only rank/name/total/percentage
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Anderson | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I am on the "Test Quiz" "mod_quiz > View" page logged in as "teacher1"
    Then I should not see "Q1" in the "block_quizleaderboard" "block"

  Scenario: Following the full leaderboard link shows the complete per-question table
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Alice     | Anderson | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And user "student1" has begun a leaderboard attempt at quiz "Test Quiz"
    When I log in as "teacher1"
    And I am on the "Test Quiz" "mod_quiz > View" page logged in as "teacher1"
    And I click on "View full leaderboard" "link" in the "block_quizleaderboard" "block"
    Then I should see "Q1"
    And I should see "Leaderboard: Test Quiz"
