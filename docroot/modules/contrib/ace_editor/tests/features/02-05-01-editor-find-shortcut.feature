@editor @ace_editor
Feature: Ace keyboard shortcuts - find
  As a content editor
  I want the Find (Ctrl-F) keyboard shortcut to work in the editor
  So that I can search the code I am editing

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: Ctrl-F opens the Ace search box
    When I navigate to "/node/2/edit"
    Then the "ace editor container" element should be visible
    When I press the "Control+f" key in the "ace editor container" element
    Then the "ace search box" element should be visible
    And there should be no JavaScript errors
