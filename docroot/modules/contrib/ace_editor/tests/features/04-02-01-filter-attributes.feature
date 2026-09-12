@filter @ace_editor @regression
Feature: The Ace filter applies per-<ace>-tag attribute overrides
  As a content author
  I want attributes on an <ace> tag to override the filter defaults
  So that a single snippet can use a different theme or syntax

  # Regression cover for js/filter.js: the per-instance settings were computed
  # but never applied (the code read the shared defaults), so a snippet's
  # theme/syntax attributes were ignored. The demo snippet sets
  # theme="twilight" while the filter default theme is "cobalt".

  Scenario: A snippet with theme="twilight" renders with the twilight theme
    Given I am an anonymous user
    When I navigate to "/node/1"
    Then the "ace filter container" element should be visible
    And the "ace filter twilight theme" element should have a count of 1
    And the "ace filter cobalt theme" element should have a count of 0
    And there should be no JavaScript errors
