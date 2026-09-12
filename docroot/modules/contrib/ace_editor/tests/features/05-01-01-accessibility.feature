@accessibility @ace_editor
Feature: Ace Editor pages meet basic accessibility expectations
  As a site owner
  I want the module's pages to pass core a11y checks
  So that the editor and rendered code are usable by everyone

  # The button accessible-name check is intentionally omitted: Drupal core's
  # Claro toolbar orientation toggle ships with only a title attribute, which
  # fails the stricter axe rule independently of this module.
  Scenario: The text format configuration page is accessible
    Given I am a logged in user with the "Webmaster" user
    When I navigate to "/admin/config/content/formats/manage/full_html"
    Then every form field should have an accessible label
    And no element should have a positive tabindex

  Scenario: The rendered filter page is accessible
    Given I am an anonymous user
    When I navigate to "/node/1"
    Then every image should have an alt attribute
    And every link should have an accessible name
    And every ARIA role should be valid
