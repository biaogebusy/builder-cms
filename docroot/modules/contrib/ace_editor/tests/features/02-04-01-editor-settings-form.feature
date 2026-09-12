@editor @ace_editor
Feature: The Ace editor configuration form
  As a site administrator
  I want the Ace editor settings to render on the text format form
  So that I can configure the theme, syntax and options

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: The settings fieldset exposes the documented controls
    When I navigate to "/admin/config/content/formats/manage/full_html"
    Then the "ace settings fieldset" element should be visible
    And the "ace theme select" element should be visible
    And the "ace syntax select" element should be visible
    And the "ace height field" element should be visible
    And the "ace line numbers checkbox" element should be attached
    And the "ace print margin checkbox" element should be attached
    And the "ace autocomplete label" element should be visible
    And there should be no JavaScript errors
