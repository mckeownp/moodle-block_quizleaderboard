@block @block_quizleaderboard @javascript
Feature: Quiz leaderboard sortable columns
  In order to quickly find top or struggling students
  As a teacher
  I need to be able to sort the leaderboard table by any column

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 | idnumber |
      | teacher1 | Tina      | Teacher  | teacher1@example.com  | T0001    |
      | student1 | Alice     | Anderson | student1@example.com  | S0003    |
      | student2 | Bob       | Brown    | student2@example.com  | S0001    |
      | student3 | Carol     | Clarke   | student3@example.com  | S0002    |
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
    And quiz "Test Quiz" contains the following questions:
      | question | page |
      | Q1       | 1    |
      | Q2       | 1    |
    And the block_quizleaderboard plugin is added with quizid for "Test Quiz" in course "C1"
    # Alice: 10/10 (100%), Bob: 5/10 (50%), Carol: 0/10 (0%) — deliberately out of
    # alphabetical and out of ID-number order, so sorting actually changes row order.
    And user "student1" has begun a leaderboard attempt at quiz "Test Quiz" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | True     |
    And question "Q1" is answered correctly by "student1" in quiz "Test Quiz"
    And question "Q2" is answered correctly by "student1" in quiz "Test Quiz"
    And user "student2" has begun a leaderboard attempt at quiz "Test Quiz" with responses:
      | slot | response |
      | 1    | True     |
      | 2    | False    |
    And question "Q1" is answered correctly by "student2" in quiz "Test Quiz"
    And question "Q2" is answered incorrectly by "student2" in quiz "Test Quiz"
    And user "student3" has begun a leaderboard attempt at quiz "Test Quiz" with responses:
      | slot | response |
      | 1    | False    |
      | 2    | False    |
    And question "Q1" is answered incorrectly by "student3" in quiz "Test Quiz"
    And question "Q2" is answered incorrectly by "student3" in quiz "Test Quiz"
    And I log in as "teacher1"
    And I view the full leaderboard for quiz "Test Quiz"

  Scenario: Default sort order is by total descending
    Then row 1 of the leaderboard table should contain "Alice Anderson"
    And row 2 of the leaderboard table should contain "Bob Brown"
    And row 3 of the leaderboard table should contain "Carol Clarke"

  Scenario: Clicking the Total header toggles ascending then descending
    When I click on the "Total" leaderboard column header
    Then row 1 of the leaderboard table should contain "Carol Clarke"
    And row 2 of the leaderboard table should contain "Bob Brown"
    And row 3 of the leaderboard table should contain "Alice Anderson"
    When I click on the "Total" leaderboard column header
    Then row 1 of the leaderboard table should contain "Alice Anderson"
    And row 3 of the leaderboard table should contain "Carol Clarke"

  Scenario: Clicking the Student header sorts alphabetically
    When I click on the "Student" leaderboard column header
    Then row 1 of the leaderboard table should contain "Alice Anderson"
    And row 2 of the leaderboard table should contain "Bob Brown"
    And row 3 of the leaderboard table should contain "Carol Clarke"
    When I click on the "Student" leaderboard column header
    Then row 1 of the leaderboard table should contain "Carol Clarke"
    And row 3 of the leaderboard table should contain "Alice Anderson"

  Scenario: Clicking the ID header sorts by student ID number, independent of name order
    When I click on the "ID" leaderboard column header
    Then row 1 of the leaderboard table should contain "Bob Brown"
    And row 2 of the leaderboard table should contain "Carol Clarke"
    And row 3 of the leaderboard table should contain "Alice Anderson"

  Scenario: Clicking a per-question column header sorts by that question's marks
    When I click on the "Q2" leaderboard column header
    Then row 1 of the leaderboard table should contain "Bob Brown"
    And row 2 of the leaderboard table should contain "Carol Clarke"
    When I click on the "Q2" leaderboard column header
    Then row 1 of the leaderboard table should contain "Alice Anderson"

  Scenario: Sort indicator updates to show which column and direction is active
    When I click on the "Total" leaderboard column header
    Then the "Total" leaderboard column header should be marked as sorted ascending
    When I click on the "Total" leaderboard column header
    Then the "Total" leaderboard column header should be marked as sorted descending

  Scenario: Switching the sorted column resets the previous column's indicator
    When I click on the "Total" leaderboard column header
    And I click on the "Student" leaderboard column header
    Then the "Student" leaderboard column header should be marked as sorted ascending
    And the "Total" leaderboard column header should not be marked as sorted

  Scenario: Percentage column sorts consistently with the total column
    When I click on the "%" leaderboard column header
    Then row 1 of the leaderboard table should contain "Carol Clarke"
    And row 3 of the leaderboard table should contain "Alice Anderson"

  Scenario: Sorting works via keyboard activation as well as mouse click
    When I press the "Enter" key while focused on the "Total" leaderboard column header
    Then row 1 of the leaderboard table should contain "Carol Clarke"
