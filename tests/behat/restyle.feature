@local @local_studiolms
Feature: Restyle an existing page or label into a hidden copy
  In order to give the text I already wrote the look of the Studio blocks
  As a teacher with the restyle capability
  I need to restyle a page or label into a hidden copy, keeping the original untouched

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name        | course | content                                      | contentformat |
      | page     | Titled page | C1     | <p>First title<br>Body text of the page.</p> | 1             |
    And the following "activities" exist:
      | activity | name         | course | intro                       |
      | label    | Label text   | C1     | <p>Label text for you.</p>  |
      | forum    | General chat | C1     | A place to talk             |

  Scenario: The landing offers to restyle what already exists
    Given the following config values are set as admin:
      | behatfakeai | 1 | local_studiolms |
    When I am on the "C1" "local_studiolms > wizard" page logged in as "teacher1"
    Then I should see "Restyle existing"

  Scenario: The list shows the pages and labels of the course and nothing else
    Given the following config values are set as admin:
      | behatfakeai | 1 | local_studiolms |
    When I am on the "C1" "local_studiolms > restyle" page logged in as "teacher1"
    Then I should see "Restyle existing activities"
    And I should see "Titled page" in the "#local-studiolms-restyle" "css_element"
    And I should see "Label text" in the "#local-studiolms-restyle" "css_element"
    And I should not see "General chat" in the "#local-studiolms-restyle" "css_element"

  @javascript
  Scenario: Polishing the text is off until the teacher asks for it
    Given the following config values are set as admin:
      | behatfakeai | 1 | local_studiolms |
    When I am on the "C1" "local_studiolms > restyle" page logged in as "teacher1"
    Then the field "Reorganise and polish the text" matches value ""

  Scenario: A student is not offered the restyle in a page
    Given the following config values are set as admin:
      | behatfakeai | 1 | local_studiolms |
    When I am on the "Titled page" "page activity" page logged in as "student1"
    Then I should not see "Restyle with StudioLMS"

  Scenario: Without an AI provider the list shows guidance instead of the pages
    When I am on the "C1" "local_studiolms > restyle" page logged in as "teacher1"
    Then I should see "AI provider required"
    And I should not see "Choose a page or label"

  Scenario: The settings of a page lead to the list with that page pointed out
    Given the following config values are set as admin:
      | behatfakeai | 1 | local_studiolms |
    And I am on the "Titled page" "page activity" page logged in as "teacher1"
    When I navigate to "Restyle with StudioLMS" in current page administration
    Then I should see "Restyle existing activities"

  @javascript
  Scenario: A page is restyled into a hidden copy and the original stays as it was
    Given the following config values are set as admin:
      | behatfakeai          | 1                                           | local_studiolms |
      | behatfakeai_response | {"blocks":[{"type":"heading","line":0,"level":2}]} | local_studiolms |
    When I am on the "C1" "local_studiolms > restyle" page logged in as "teacher1"
    And I click on "[aria-label='Restyle Titled page']" "css_element"
    Then I should see "is ready" in the "#local-studiolms-restyle" "css_element"
    And I should see "Open the copy to adjust it"
    And I am on "Course 1" course homepage with editing mode on
    And I should see "Hidden from students" in the "Titled page (StudioLMS)" "activity"

  @javascript
  Scenario: With polishing on, the copy holds the text the AI wrote
    Given the following config values are set as admin:
      | behatfakeai          | 1 | local_studiolms |
      | behatfakeai_response | {"blocks":[{"type":"heading","text":"First title","level":2},{"type":"paragraph","html":"Body text of the page, polished."}]} | local_studiolms |
    When I am on the "C1" "local_studiolms > restyle" page logged in as "teacher1"
    And I set the field "Reorganise and polish the text" to "1"
    And I click on "[aria-label='Restyle Titled page']" "css_element"
    Then I should see "is ready" in the "#local-studiolms-restyle" "css_element"
    And I am on the "Titled page (StudioLMS)" "page activity" page
    And I should see "Body text of the page, polished."

  @javascript
  Scenario: A polished text with a number that is not in the original is refused and nothing is copied
    Given the following config values are set as admin:
      | behatfakeai          | 1 | local_studiolms |
      | behatfakeai_response | {"blocks":[{"type":"paragraph","html":"First title and the body text of the page in 99 ways."}]} | local_studiolms |
    When I am on the "C1" "local_studiolms > restyle" page logged in as "teacher1"
    And I set the field "Reorganise and polish the text" to "1"
    And I click on "[aria-label='Restyle Titled page']" "css_element"
    Then I should see "nothing was copied"
    And I am on "Course 1" course homepage
    And I should not see "Titled page (StudioLMS)"
