@editor @ace_editor
Feature: A disabled field renders a read-only Ace editor
  As a content editor
  I want Ace to respect a form field that has been disabled
  So that a read-only field cannot be edited through the editor

  Background:
    Given I am a logged in user with the "Webmaster" user

  Scenario: A disabled field cannot be edited through the editor
    When I navigate to "/node/2/edit?ace_test_disable_body=1"
    Then the "ace editor container" element should be visible
    And the "ace editor readonly input" element should be attached
    When I type "TYPED_INTO_DISABLED" into the Ace editor with the keyboard
    Then the Ace editor content should not contain "TYPED_INTO_DISABLED"
    And there should be no JavaScript errors

  Scenario: A field that is not disabled stays editable
    When I navigate to "/node/2/edit"
    Then the "ace editor container" element should be visible
    And the "ace editor readonly input" element should have a count of 0
    When I type "TYPED_INTO_ENABLED" into the Ace editor with the keyboard
    Then the Ace editor content should contain "TYPED_INTO_ENABLED"
    And there should be no JavaScript errors
