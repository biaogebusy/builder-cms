@editor @ace_editor
Feature: The Ace editor syncs back to the underlying textarea
  As a content editor
  I want my edits in the Ace editor to reach the hidden textarea
  So that the form submits the code I typed

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Typing in the Ace editor updates the hidden body textarea
    When I navigate to "/node/2/edit"
    And the Ace editor should be attached to the "body textarea" field
    And I type "<h2>Synced from Ace</h2>" into the Ace editor
    Then the "body textarea" field value should contain "<h2>Synced from Ace</h2>"
    And there should be no JavaScript errors
