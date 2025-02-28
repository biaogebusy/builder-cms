<?php

namespace Drupal\Tests\state_machine\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the state transition form.
 *
 * @group state_machine
 */
class StateTransitionFormTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'state_machine_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->drupalCreateUser(['administer entity_test content', 'view test entity']);
    $this->drupalLogin($user);
  }

  /**
   * Tests the confirmation form rendered in a modal.
   */
  public function testConfirmationFormWithModal() {
    $view_display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test_with_bundle',
      'bundle' => 'first',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $view_display->setComponent('field_state', [
      'type' => 'state_transition_form',
      'settings' => [
        'require_confirmation' => TRUE,
        'use_modal' => TRUE,
      ],
    ]);
    $view_display->save();
    $first_entity = EntityTestWithBundle::create([
      'type' => 'first',
      'name' => 'First',
    ]);
    $first_entity->save();
    $this->drupalGet($first_entity->toUrl('canonical'));
    $this->assertSession()->pageTextContains('First');

    $buttons = $this->xpath('//form[@id="state-machine-transition-form-entity-test-with-bundle-field-state-1"]/div/a');
    $this->assertCount(2, $buttons);
    $this->assertEquals('Create', $buttons[0]->getText());
    $this->assertEquals('Cancel', $buttons[1]->getText());
    // Click the Create link.
    $buttons[0]->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Are you sure you want to apply this transition?');
    $this->assertSession()->pageTextContains('From: New');
    $this->assertSession()->pageTextContains('To: Fulfillment');
    $this->assertSession()->pageTextContains('Transition: Create');
    $this->assertSession()->pageTextContains('Test entity with bundle: First');
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->assertSession()->linkExists('Cancel');
    $this->assertSession()->buttonExists('Confirm');
  }

}
