<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\entity_test\Entity\EntityTestMulChanged;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\content_lock\Tools\LogoutTrait;
use Drupal\user\UserInterface;

/**
 * Trait for testing content lock.
 */
trait ContentLockTestTrait {
  use CountLocksTestTrait;
  use LogoutTrait;

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $admin;

  /**
   * User without break lock permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user1;

  /**
   * User with break lock permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user2;

  /**
   * The entity to test.
   *
   * @var \Drupal\entity_test\Entity\EntityTestMul
   */
  protected EntityTestMul $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $additional_permissions = [];
    if (\Drupal::moduleHandler()->moduleExists('content_translation')) {
      $additional_permissions = [
        'administer languages',
        'administer content translation',
        'create content translations',
        'update content translations',
        'delete content translations',
        'translate any entity',
      ];
    }

    $this->admin = $this->drupalCreateUser(array_merge([
      'administer entity_test content',
      'administer content lock',
    ], $additional_permissions));

    $this->user1 = $this->drupalCreateUser(array_merge([
      'view test entity',
      'administer entity_test content',
    ], $additional_permissions));
    $this->user2 = $this->drupalCreateUser(array_merge([
      'view test entity',
      'administer entity_test content',
      'break content lock',
    ], $additional_permissions));

    if (\Drupal::moduleHandler()->moduleExists('content_translation')) {
      ConfigurableLanguage::create(['id' => 'de'])->save();
      $this->drupalLogin($this->admin);
      $this->drupalGet('admin/config/regional/content-language');
      $edit = [
        'entity_types[entity_test_mul_changed]' => 'entity_test_mul_changed',
        'settings[entity_test_mul_changed][entity_test_mul_changed][translatable]' => 1,
        'settings[entity_test_mul_changed][entity_test_mul_changed][fields][name]' => 1,
        'settings[entity_test_mul_changed][entity_test_mul_changed][fields][created]' => 1,
        'settings[entity_test_mul_changed][entity_test_mul_changed][fields][user_id]' => 1,
        'settings[entity_test_mul_changed][entity_test_mul_changed][fields][field_test_text]' => 1,
      ];
      $this->drupalGet('admin/config/regional/content-language');
      $this->submitForm($edit, 'Save configuration');
      $this->rebuildContainer();
    }

    $this->entity = EntityTestMulChanged::create([
      'name' => $this->randomMachineName(),
    ]);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function submitForm(array $edit, $submit, $form_html_id = NULL) {
    // Rebuild routes and the container if we save the config form in a test.
    $rebuild = $submit === 'Save configuration' && str_ends_with($this->getSession()->getCurrentUrl(), 'admin/config/content/content_lock');

    parent::submitForm($edit, $submit, $form_html_id);

    if ($rebuild) {
      $this->rebuildAll();
    }
  }

}
