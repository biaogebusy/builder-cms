<?php

namespace Drupal\Tests\state_machine\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the state transition form.
 *
 * @group state_machine
 */
class StateTransitionFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'options',
    'state_machine',
    'state_machine_test',
    'views',
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
   * Tests the transition form.
   */
  public function testForm() {
    $first_entity = EntityTestWithBundle::create([
      'type' => 'first',
      'name' => 'First',
    ]);
    $first_entity->save();

    $second_entity = EntityTestWithBundle::create([
      'type' => 'second',
      'name' => 'Second',
      'field_state' => 'validation',
    ]);
    $second_entity->save();

    $this->drupalGet('/state-machine-test');
    $this->assertSession()->pageTextContains('First');
    $this->assertSession()->pageTextContains('New');
    $buttons = $this->xpath('//form[@id="state-machine-transition-form-entity-test-with-bundle-field-state-1"]/div/input');
    $this->assertCount(2, $buttons);
    $this->assertEquals('Create', $buttons[0]->getValue());
    $this->assertEquals('Cancel', $buttons[1]->getValue());

    $this->assertSession()->pageTextContains('Second');
    $this->assertSession()->pageTextContains('Validation');
    $buttons = $this->xpath('//form[@id="state-machine-transition-form-entity-test-with-bundle-field-state-2"]/div/input');
    $this->assertCount(1, $buttons);
    $this->assertEquals('Validate', $buttons[0]->getValue());

    // Click the Validate button.
    $buttons[0]->click();
    $this->assertSession()->pageTextContains('First');
    $this->assertSession()->pageTextContains('New');
    $buttons = $this->xpath('//form[@id="state-machine-transition-form-entity-test-with-bundle-field-state-1"]/div/input');
    $this->assertCount(2, $buttons);
    $this->assertEquals('Create', $buttons[0]->getValue());
    $this->assertEquals('Cancel', $buttons[1]->getValue());
    $this->assertSession()->buttonExists('Create');

    $this->assertSession()->pageTextContains('Second');
    $this->assertSession()->pageTextContains('Fulfillment');
    $buttons = $this->xpath('//form[@id="state-machine-transition-form-entity-test-with-bundle-field-state-2"]/div/input');
    $this->assertCount(1, $buttons);
    $this->assertEquals('Fulfill', $buttons[0]->getValue());
  }

  /**
   * Tests the confirmation form.
   */
  public function testConfirmationForm() {
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
    $this->assertSession()->linkExists('Create');
    // Click the Create button.
    $buttons[0]->click();
    $this->assertSession()->pageTextContains('Are you sure you want to apply this transition?');
    $this->assertSession()->pageTextContains('From: New');
    $this->assertSession()->pageTextContains('To: Fulfillment');
    $this->assertSession()->pageTextContains('Transition: Create');
    $this->assertSession()->pageTextContains('Test entity with bundle: First');
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->assertSession()->linkExists('Cancel');
    $this->submitForm([], 'Confirm');

    $buttons = $this->xpath('//form[@id="state-machine-transition-form-entity-test-with-bundle-field-state-1"]/div/a');
    $this->assertCount(1, $buttons);
    $this->assertEquals('Fulfill', $buttons[0]->getText());

    $transitions = [
      'create' => 403,
      'cancel' => 403,
      'fulfill' => 200,
    ];
    // Ensure the state transition form route doesn't allow access for
    // transitions that are not allowed.
    foreach ($transitions as $transition_id => $expected_status_code) {
      $route = Url::fromRoute('entity.entity_test_with_bundle.state_transition_form', [
        'entity_test_with_bundle' => $first_entity->id(),
        'field_name' => 'field_state',
        'transition_id' => $transition_id,
      ]);
      $this->drupalGet($route);
      $this->assertSession()->statusCodeEquals($expected_status_code);
    }
  }

}
