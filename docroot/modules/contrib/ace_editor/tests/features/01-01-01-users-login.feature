@setup @ace_editor
Feature: Provision the Ace Editor test site
  As the site owner
  I want one user per role created through the admin UI
  So that the editor, formatter and filter scenarios have what they expect

  Scenario: The Webmaster signs in and provisions the testing users
    Given I am a logged in user with the "Webmaster" user
    And I add testing users
    When I navigate to "/admin/config/content/formats"
    Then the "drupal page heading" element should contain text "Text formats"
    And there should be no JavaScript errors
