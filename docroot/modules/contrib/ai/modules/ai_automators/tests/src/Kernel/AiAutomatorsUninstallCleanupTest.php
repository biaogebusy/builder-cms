<?php

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\ai_automators\AiAutomatorStatusField;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;

/**
 * Tests uninstall cleanup for status field and display references.
 *
 * @group ai_automators
 */
class AiAutomatorsUninstallCleanupTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'options',
    'file',
    'image',
    'media',
    'views',
    'media_library',
    'text',
    'filter',
    'key',
    'token',
    'ai',
    'ai_automators',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('automator_chain');
    $this->installSchema('file', 'file_usage');
    $this->installSchema('user', ['users_data']);
    $this->installConfig([
      'system',
      'field',
      'file',
      'image',
      'media',
      'filter',
      'media_library',
    ]);

    $this->createImageMediaType();
    $this->createAutomatorConfig('direct');
    \Drupal::service('ai_automator.status_field')->modifyStatusField('media', 'image');
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    $this->addStatusFieldToDisplays();
  }

  /**
   * Tests uninstall removes status field config and display references.
   */
  public function testUninstallRemovesStatusFieldConfigAndDisplayReferences(): void {
    $field_name = AiAutomatorStatusField::FIELD_NAME;
    $entity_type_manager = \Drupal::entityTypeManager();

    $this->assertNotNull(FieldStorageConfig::loadByName('media', $field_name));
    $this->assertNotNull(FieldConfig::loadByName('media', 'image', $field_name));

    foreach (['default', 'media_library'] as $mode) {
      $form_display = $entity_type_manager->getStorage('entity_form_display')->load("media.image.$mode");
      $view_display = $entity_type_manager->getStorage('entity_view_display')->load("media.image.$mode");
      $this->assertNotNull($form_display);
      $this->assertNotNull($view_display);
      $this->assertNotNull($form_display->getComponent($field_name));
      $this->assertNotNull($view_display->getComponent($field_name));
    }

    \Drupal::service('module_installer')->uninstall(['ai_automators']);

    $this->assertFalse(\Drupal::service('module_handler')->moduleExists('ai_automators'));
    $this->assertNull(FieldStorageConfig::loadByName('media', $field_name));
    $this->assertNull(FieldConfig::loadByName('media', 'image', $field_name));

    foreach (['default', 'media_library'] as $mode) {
      $form_display = $entity_type_manager->getStorage('entity_form_display')->load("media.image.$mode");
      $view_display = $entity_type_manager->getStorage('entity_view_display')->load("media.image.$mode");
      $this->assertNotNull($form_display);
      $this->assertNotNull($view_display);
      $this->assertNull($form_display->getComponent($field_name));
      $this->assertNull($view_display->getComponent($field_name));
    }
  }

  /**
   * Creates the media image bundle and source field.
   */
  protected function createImageMediaType(): void {
    $media_type = MediaType::create([
      'id' => 'image',
      'label' => 'Image',
      'source' => 'image',
    ]);
    $media_type->save();

    $source_field = $media_type->getSource()->createSourceField($media_type);
    $source_field->getFieldStorageDefinition()->save();
    $source_field->save();
    $media_type
      ->set('source_configuration', [
        'source_field' => $source_field->getName(),
      ])
      ->save();
  }

  /**
   * Creates an AI automator config entity for the image field.
   *
   * @param string $worker_type
   *   The worker type plugin ID.
   */
  protected function createAutomatorConfig(string $worker_type) {
    $automator = \Drupal::entityTypeManager()
      ->getStorage('ai_automator')
      ->create([
        'id' => 'media.image.field_media_image.default',
        'label' => 'Image Generation',
        'rule' => 'llm_media_image_generation',
        'input_mode' => 'base',
        'weight' => 100,
        'worker_type' => $worker_type,
        'entity_type' => 'media',
        'bundle' => 'image',
        'field_name' => 'field_media_image',
        'edit_mode' => FALSE,
        'base_field' => 'name',
        'prompt' => '{{ context }}',
        'token' => '',
        'plugin_config' => [
          'automator_enabled' => 1,
          'automator_rule' => 'llm_media_image_generation',
          'automator_mode' => 'base',
          'automator_base_field' => 'name',
          'automator_prompt' => '{{ context }}',
          'automator_token' => '',
          'automator_edit_mode' => 0,
          'automator_label' => 'Image Generation',
          'automator_weight' => '100',
          'automator_worker_type' => $worker_type,
          'automator_ai_provider' => 'echoai',
          'automator_ai_model' => 'default',
          'automator_llm_media_type' => 'image',
        ],
      ]);
    $automator->save();
  }

  /**
   * Adds the status field to form/view displays for media:image.
   */
  protected function addStatusFieldToDisplays(): void {
    $field_name = AiAutomatorStatusField::FIELD_NAME;

    foreach (['default', 'media_library'] as $mode) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'media',
        'mode' => $mode,
        'bundle' => 'image',
      ]);
      $form_display->setComponent($field_name, [
        'type' => 'options_select',
        'weight' => 99,
        'region' => 'content',
        'settings' => [],
        'third_party_settings' => [],
      ]);
      $form_display->save();
      $view_display = EntityViewDisplay::create([
        'targetEntityType' => 'media',
        'mode' => $mode,
        'bundle' => 'image',
      ]);
      $view_display->setComponent($field_name, [
        'type' => 'list_default',
        'weight' => 99,
        'label' => 'hidden',
        'region' => 'content',
        'settings' => [],
        'third_party_settings' => [],
      ]);
      $view_display->save();
    }
  }

}
