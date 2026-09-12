@editor @ace_editor @regression
Feature: The Ace Editor field widget edits a field without a text format
  As a content editor
  I want a plain long text field to use the Ace editor
  So that I can edit code in a field that carries no text format

  # Coverage for issue #2933546: a plain long text field has no text format, so
  # the text editor integration cannot reach it. The Article carries a
  # "field_ace_code" string_long field configured with the Ace Editor widget,
  # theme twilight and syntax css.

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The widget replaces the textarea with an Ace editor
    When I navigate to "/node/add/article"
    Then the global Ace library should be available
    And the "ace code widget container" element should be visible
    And the "ace code widget twilight theme" element should have a count of 1
    And the Ace widget "field_ace_code" should use the "css" mode
    And there should be no JavaScript errors

  Scenario: The widget renders an existing field value
    When I navigate to "/node/2/edit"
    Then the "ace code widget container" element should be visible
    And the "ace code widget gutter cell" element should have at least a count of 1
    And there should be no JavaScript errors
