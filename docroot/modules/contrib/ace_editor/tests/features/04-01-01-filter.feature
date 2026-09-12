@filter @ace_editor
Feature: The Ace text filter renders <ace> snippets
  As a content author
  I want <ace> … </ace> tags turned into highlighted, read-only editors
  So that I can embed code snippets in body text

  Scenario: A page with an <ace> snippet renders a highlighted editor
    Given I am an anonymous user
    When I navigate to "/node/1"
    Then the global Ace library should be available
    And the Drupal behavior "ace_filter" should be registered
    And the "ace filter pre" element should have a count of 1
    And the "ace filter container" element should be visible
    And the "ace filter gutter cell" element should have at least a count of 1
    And the "ace filter container" element should contain text "function hello"
    And there should be no JavaScript errors
