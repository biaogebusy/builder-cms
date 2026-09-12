<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_lock\Tools\LogoutTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Block tests.
 *
 * @group content_lock
 */
#[RunTestsInSeparateProcesses]
class ContentLockBlockTest extends BrowserTestBase {
  use LogoutTrait;
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'content_lock',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    if (floatval(\Drupal::VERSION) < 10) {
      $this->markTestSkipped("This test fails on Drupal 9");
    }
    parent::setUp();
  }

  /**
   * Creates a custom block.
   *
   * @param bool|string $title
   *   (optional) Title of block. When no value is given uses a random name.
   *   Defaults to FALSE.
   * @param string $bundle
   *   (optional) Bundle name. Defaults to 'basic'.
   * @param bool $save
   *   (optional) Whether to save the block. Defaults to TRUE.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   Created custom block.
   */
  protected function createBlockContent(string|false $title = FALSE, string $bundle = 'basic', bool $save = TRUE): BlockContent {
    $title = $title ?: $this->randomMachineName();
    $block_content = BlockContent::create([
      'info' => $title,
      'type' => $bundle,
      'langcode' => 'en',
    ]);
    if ($block_content && $save === TRUE) {
      $block_content->save();
    }
    return $block_content;
  }

  /**
   * Creates a custom block type (bundle).
   *
   * @param string $label
   *   The block type label.
   * @param bool $create_body
   *   Whether to create the body field.
   *
   * @return \Drupal\block_content\Entity\BlockContentType
   *   Created custom block type.
   */
  protected function createBlockContentType(string $label, bool $create_body = FALSE): BlockContentType {
    $bundle = BlockContentType::create([
      'id' => $label,
      'label' => $label,
      'revision' => FALSE,
    ]);
    $bundle->save();
    if ($create_body) {
      $this->createBodyField('block_content', $bundle->id());
    }
    return $bundle;
  }

  /**
   * Creates a field of a body field storage on the specified bundle.
   *
   * @todo Remove this when Drupal 10 support is removed and use
   * BodyFieldCreationTrait instead.
   *
   * @param string $entityType
   *   The type of entity the field will be attached to.
   * @param string $bundle
   *   The bundle name of the entity the field will be attached to.
   * @param string $fieldName
   *   (optional) The name of the field. Defaults to 'body'.
   * @param string $fieldLabel
   *   (optional) The label for the field. Defaults to 'Body'.
   * @param int $cardinality
   *   (optional) The cardinality of the field. Defaults to 1.
   */
  protected function createBodyField(string $entityType, string $bundle, string $fieldName = 'body', string $fieldLabel = 'Body', int $cardinality = 1): void {
    // Look for or add the specified field to the requested entity bundle.
    $fieldStorage = FieldStorageConfig::loadByName($entityType, $fieldName);
    if (!$fieldStorage) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'type' => 'text_long',
        'entity_type' => $entityType,
        'cardinality' => $cardinality,
        'persist_with_no_fields' => TRUE,
      ])->save();
      $fieldStorage = FieldStorageConfig::loadByName($entityType, $fieldName);
    }
    if (!FieldConfig::loadByName($entityType, $bundle, $fieldName)) {
      FieldConfig::create([
        'field_storage' => $fieldStorage,
        'bundle' => $bundle,
        'label' => $fieldLabel,
        'settings' => [
          'allowed_formats' => [],
        ],
      ])->save();

      /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
      $display_repository = \Drupal::service('entity_display.repository');

      // Assign widget settings for the default form mode.
      $display_repository->getFormDisplay($entityType, $bundle)
        ->setComponent('body', [
          'type' => 'text_textarea',
        ])
        ->save();

      // Assign display settings for the 'default' and 'teaser' view modes.
      $display_repository->getViewDisplay($entityType, $bundle)
        ->setComponent('body', [
          'label' => 'hidden',
          'type' => 'text_default',
        ])
        ->save();

      // The teaser view mode is created by the Standard profile and might
      // not exist.
      $view_modes = $display_repository->getViewModes($entityType);
      if (isset($view_modes['teaser'])) {
        $display_repository->getViewDisplay($entityType, $bundle, 'teaser')
          ->setComponent('body', [
            'label' => 'hidden',
            'type' => 'text_trimmed',
          ])
          ->save();
      }
    }
  }

  /**
   * Test simultaneous edit on block.
   */
  public function testContentLockBlock(): void {

    // Create block.
    $this->createBlockContentType('basic', TRUE);
    $block1 = $this->createBlockContent('Block 1');

    $admin = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'administer content lock',
      'view the administration theme',
    ]);

    $user1 = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'access content',
      'view the administration theme',
    ]);
    $user2 = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'break content lock',
      'view the administration theme',
    ]);

    // We protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'block_content[bundles][basic]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // We lock block1.
    $this->drupalLogin($user1);
    // Edit a node without saving.
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit block1.
    $this->drupalLogin($user2);
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session->pageTextContains("This content is being edited by the user {$user1->getDisplayName()} and is therefore locked to prevent changes by other users.");
    $assert_session->linkExists('Break the lock.');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We save block1 and unlock it.
    $this->drupalLogin($user1);
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet('/admin/content/block/' . $block1->id());
    $this->submitForm([], 'Save');

    // We lock block1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session->pageTextContains('This content is now locked against simultaneous editing.');

    // Other user can not edit block1.
    $this->drupalLogin($user1);
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session->pageTextContains("This content is being edited by the user {$user2->getDisplayName()} and is therefore locked to prevent changes by other users.");
    $assert_session->linkNotExists('Break the lock.');
    $submit = $assert_session->buttonExists('edit-submit');
    $this->assertTrue($submit->hasAttribute('disabled'));

    // We unlock block1 with user2.
    $this->drupalLogin($user2);
    // Edit a node without saving.
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $assert_session->pageTextContains('This content is now locked by you against simultaneous editing.');
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $this->submitForm([], 'Save');
    $assert_session->pageTextContains('has been updated.');
  }

  /**
   * Tests deleting blocks with content locks.
   *
   * @covers content_lock_entity_access
   */
  public function testContentLockBlockDeleteAccess(): void {
    // Create two test blocks.
    $this->createBlockContentType('basic', TRUE);
    $block1 = $this->createBlockContent('Block for user without break permission');
    $block2 = $this->createBlockContent('Block for user with break permission');

    $admin = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'administer content lock',
      'delete any basic block content',
      'view the administration theme',
    ]);

    // User without break lock permission.
    $user1 = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'delete any basic block content',
      'access content',
      'view the administration theme',
    ]);

    // User with break lock permission.
    $user2 = $this->drupalCreateUser([
      'administer blocks',
      'administer block content',
      'delete any basic block content',
      'access content',
      'break content lock',
      'view the administration theme',
    ]);

    // We protect the bundle created.
    $this->drupalLogin($admin);
    $edit = [
      'block_content[bundles][basic]' => 1,
    ];
    $this->drupalGet('admin/config/content/content_lock');
    $this->submitForm($edit, 'Save configuration');

    // Lock both blocks.
    $this->drupalGet("/admin/content/block/{$block1->id()}");
    $this->drupalGet("/admin/content/block/{$block2->id()}");

    // Test user1 (without break lock permission) cannot delete the locked
    // block.
    $this->drupalLogin($user1);
    $this->drupalGet("/admin/content/block/{$block1->id()}/delete");
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(403);

    // Test user2 (with break lock permission) can delete the locked block.
    $this->drupalLogin($user2);
    $this->drupalGet("/admin/content/block/{$block2->id()}/delete");
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Are you sure you want to delete');
    // In order to delete the user must break the lock. Breaking the lock is
    // tested above.
    $assert_session->pageTextContains('Break the lock');
  }

}
