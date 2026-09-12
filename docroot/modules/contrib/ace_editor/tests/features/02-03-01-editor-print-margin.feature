@editor @ace_editor @regression
Feature: The Ace editor honours the print-margin setting
  As a content editor
  I want the "Show print margin" setting to actually show the margin
  So that the configured 80-character guide appears

  # Regression cover for the print_margins / print_margin key mismatch: the
  # editor read a non-existent `print_margin` key, so the margin never showed.

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The print margin is rendered when print_margins is enabled
    When I navigate to "/node/2/edit"
    Then the Ace editor should be attached to the "body textarea" field
    And the "ace editor print margin" element should be attached
    And the "ace editor print margin" element should show the print margin
    And there should be no JavaScript errors
