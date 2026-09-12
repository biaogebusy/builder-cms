<?php

namespace Drupal\Tests\eck\Functional\Views;

use Drupal\Tests\BrowserTestBase;
use Drupal\views\Views;

/**
 * Tests the ECK entity label Views field.
 *
 * @group eck
 */
class EntityLabelViewsFieldTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'views',
    'eck',
    'eck_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A simple user with 'access content' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a test user.
    $this->user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($this->user);

    // Create a test entity.
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('test_type');
    $entity = $storage->create([
      'type' => 'test_bundle',
      'title' => 'test title',
    ]);
    $entity->save();

    // Make sure the new view route is available.
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests the ECK entity label field.
   */
  public function testEntityLabelField() {
    $this->drupalGet('eck-test/views/field-label');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementContains('css', 'td.views-field-label', 'test title');
  }

  /**
   * Tests the ECK entity label field with a link.
   */
  public function testEntityLabelFieldWithLink() {
    $view = Views::getView('eck_test_field_label');
    $fields = $view->getDisplay()->getOption('fields');
    $fields['label']['link_to_entity'] = TRUE;
    $view->getDisplay()->overrideOption('fields', $fields);
    $view->save();

    $this->drupalGet('eck-test/views/field-label');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementContains('css', 'td.views-field-label a', 'test title');
  }

}
