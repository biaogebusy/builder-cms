@smoke @ace_editor
Feature: Smoke - the Ace Editor module is wired up
  As a site administrator
  I want the module's editor, formatter and filter to be available
  So that the Ace Editor integration can be exercised

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The Full HTML format exposes the Ace Editor settings
    When I navigate to "/admin/config/content/formats/manage/full_html"
    Then the "format editor select" element should be visible
    And the "ace settings fieldset" element should be visible
    And there should be no JavaScript errors

  Scenario: The text formats overview page lists Full HTML
    When I navigate to "/admin/config/content/formats"
    Then the "drupal page heading" element should contain text "Text formats"
    And I should see "Full HTML"
