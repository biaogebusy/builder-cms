@editor @ace_editor
Feature: The Ace text editor attaches to a node form
  As a content editor
  I want the Ace editor to replace the plain body textarea
  So that I edit raw HTML with syntax highlighting

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Editing an article shows the Ace editor on the body field
    When I navigate to "/node/2/edit"
    Then the global Ace library should be available
    And the Drupal editor "ace_editor" should be registered
    And the Ace editor should be attached to the "body textarea" field
    And the "ace editor container" element should have a count of 1
    And the "ace editor gutter cell" element should have at least a count of 1
    And there should be no JavaScript errors
