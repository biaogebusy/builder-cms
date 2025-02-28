<?php

namespace Drupal\Tests\views_bulk_operations\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Base class for VBO browser tests.
 */
abstract class ViewsBulkOperationsFunctionalTestBase extends BrowserTestBase {

  const TEST_NODE_COUNT = 15;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'views',
    'views_bulk_operations',
    'views_bulk_operations_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create some nodes for testing.
    $this->drupalCreateContentType(['type' => 'page']);

    $this->testNodes = [];
    $time = $this->container->get('datetime.time')->getRequestTime();
    for ($i = 0; $i < static::TEST_NODE_COUNT; $i++) {
      // Ensure nodes are sorted in the same order they are inserted in the
      // array.
      $time -= $i;
      $this->testNodes[] = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Title ' . $i,
        'sticky' => FALSE,
        'created' => $time,
        'changed' => $time,
      ]);
    }

  }

  /**
   * Helper function that gets configuration for a selected view.
   *
   * @param string|null $path
   *   The path of the View page that includes VBO.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $button_text
   *   The form submit button text.
   * @param int[] $selection
   *   The selected items' indexes.
   * @param array $data
   *   Additional parameters for the submitted form.
   */
  protected function executeAction($path, TranslatableMarkup $button_text, array $selection = [], array $data = []) {
    foreach ($selection as $index) {
      $data["views_bulk_operations_bulk_form[$index]"] = TRUE;
    }
    $this->drupalPostForm($path, $data, $button_text);
  }

}
