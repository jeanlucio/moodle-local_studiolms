@local @local_studiolms
Feature: The StudioLMS generation wizard landing
  In order to start building a course with AI
  As a teacher with the generate capability
  I need the in-course wizard to offer the three generation modes

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: A teacher can open the wizard and see the generation modes
    When I am on the "C1" "local_studiolms > wizard" page logged in as "teacher1"
    Then I should see "what would you like to do today"
    And I should see "Single Activity"
    And I should see "Section"
    And I should see "Full Course"
