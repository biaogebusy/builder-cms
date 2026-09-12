<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the Diff module plugins.
 */
abstract class DiffPluginTestBase extends DiffTestBase {

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['diff_test', 'link', 'options'];

  /**
   * A storage instance for the entity form display.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected $formDisplay;

  /**
   * A storage instance for the entity view display.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $viewDisplay;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->rootUser);
  }

  /**
   * Get the form display storage.
   */
  protected function getFormDisplayStorage(): EntityStorageInterface {
    return \Drupal::entityTypeManager()->getStorage('entity_form_display');
  }

  /**
   * Get the form display storage.
   */
  protected function getViewDisplayStorage(): EntityStorageInterface {
    return \Drupal::entityTypeManager()->getStorage('entity_view_display');
  }

  /**
   * Load an entity form display.
   *
   * @param string $id
   *   The entity form display ID.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The loaded entity form display.
   */
  protected function loadFormDisplay(string $id): EntityFormDisplayInterface {
    $display = $this->getFormDisplayStorage()->load($id);
    \assert($display instanceof EntityFormDisplayInterface);
    return $display;
  }

  /**
   * Load an entity view display.
   *
   * @param string $id
   *   The entity view display ID.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   The loaded entity view display.
   */
  protected function loadViewDisplay(string $id): EntityViewDisplayInterface {
    $display = $this->getViewDisplayStorage()->load($id);
    \assert($display instanceof EntityViewDisplayInterface);
    return $display;
  }

}
