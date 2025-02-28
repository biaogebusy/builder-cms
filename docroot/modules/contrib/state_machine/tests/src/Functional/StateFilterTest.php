<?php

namespace Drupal\Tests\state_machine\Functional;

use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Views state filter.
 *
 * @group state_machine
 */
class StateFilterTest extends BrowserTestBase {

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

    $user = $this->drupalCreateUser(['administer entity_test content']);
    $this->drupalLogin($user);
  }

  /**
   * Tests the filter.
   */
  public function testFilter() {
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

    $this->assertSession()->pageTextContains('Second');
    $this->assertSession()->pageTextContains('Validation');

    // Confirm that the states from both workflows are in the dropdown.
    $expected_options = [
      'All' => '- Any -',
      'new' => 'New',
      'validation' => 'Validation',
      'fulfillment' => 'Fulfillment',
      'completed' => 'Completed',
      'canceled' => 'Canceled',
    ];
    $elements = $this->xpath('//select[@name="field_state_value"]/option');
    $found_options = [];
    foreach ($elements as $element) {
      $found_options[$element->getValue()] = $element->getText();
    }
    $this->assertEquals($expected_options, $found_options);
  }

}
