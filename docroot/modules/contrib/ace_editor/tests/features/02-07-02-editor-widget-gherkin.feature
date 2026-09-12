@editor @ace_editor @regression
Feature: Editing a Gherkin script in an Ace Editor widget field
  As a test engineer
  I want a field that holds a .feature script to use the Gherkin mode
  So that I can write and read Gherkin in the site itself

  # Ace ships a "gherkin" mode, so a plain long text field configured with the
  # Ace Editor widget and syntax "gherkin" is all a Gherkin script needs. The
  # Article carries "field_gherkin_script" configured that way (issue #2933546).

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The Gherkin field loads the Gherkin mode
    When I navigate to "/node/add/article"
    Then the global Ace library should be available
    And the "ace gherkin widget container" element should be visible
    And the Ace widget "field_gherkin_script" should use the "gherkin" mode
    And the "ace gherkin widget gutter cell" element should have at least a count of 1
    And there should be no JavaScript errors

  Scenario: A Gherkin script keeps its indentation through a save
    When I navigate to "/node/2/edit"
    Then the "ace gherkin widget container" element should be visible
    And the Ace widget "field_gherkin_script" should use the "gherkin" mode
    And the Ace widget "field_gherkin_script" content should contain "Scenario: The editor highlights the script"
    And the Ace widget "field_gherkin_script" content should contain "    Given I am a logged in user"
    And there should be no JavaScript errors
