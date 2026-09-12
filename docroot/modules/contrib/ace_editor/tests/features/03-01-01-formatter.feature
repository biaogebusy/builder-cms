@formatter @ace_editor
Feature: The Ace Format field formatter
  As a site visitor
  I want code fields displayed with read-only syntax highlighting
  So that snippets are easy to read

  Scenario: An article body shows a read-only highlighted Ace editor
    Given I am an anonymous user
    When I navigate to "/node/2"
    Then the global Ace library should be available
    And the Drupal behavior "ace_formatter" should be registered
    And the "ace formatter wrap" element should be visible
    And the "ace formatter container" element should have a count of 1
    And the "ace formatter gutter cell" element should have at least a count of 1
    And there should be no JavaScript errors
