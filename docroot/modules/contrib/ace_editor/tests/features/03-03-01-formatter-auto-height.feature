@formatter @ace_editor @regression
Feature: The Ace Format formatter supports an auto height
  As a site builder
  I want to set an Ace Format field's height to "auto"
  So that the editor grows to fit its content instead of a fixed height

  # Regression cover for issue #2846046: a height of "auto" was applied as a CSS
  # height, which collapsed the read-only editor and hid the content. It now
  # grows to fit the content through Ace's line sizing. The demo Article body is
  # displayed with height "auto"; a collapsed editor is only a few pixels tall.

  Scenario: An auto-height formatter grows to show its content
    Given I am an anonymous user
    When I navigate to "/node/2"
    Then the global Ace library should be available
    And the "ace formatter container" element should be visible
    And the "ace formatter container" element should be taller than 20 pixels within 15 seconds
    And the "ace formatter gutter cell" element should have at least a count of 1
    And there should be no JavaScript errors
