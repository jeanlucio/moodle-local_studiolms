@local @local_studiolms
Feature: StudioLMS admin settings
  In order to understand how StudioLMS sources its AI
  As an administrator
  I need the settings page to explain the AI provider chain without asking for keys

  Scenario: The settings page explains the AI source and asks for no key
    Given I log in as "admin"
    When I am on the "local_studiolms > settings" page
    Then I should see "Artificial intelligence"
    And I should see "There is no API key to configure here"
    And I should not see "Preferred AI provider"
