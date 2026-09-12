<?php

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests text-to-image media generation via field widget processing.
 *
 * This tests the integration of the FWA (Field Widget Action) trigger path
 * with the FieldWidgetProcessing automator worker. It exercises the same
 * code path as when a user clicks the "Text to Image Media Library" button
 * on a content form.
 *
 * @group ai_automators
 */
class TextToImageMediaFieldWidgetProcessingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'node',
    'text',
    'token',
    'filter',
    'key',
    'ai',
    'ai_test',
    'ai_automators',
    'field_widget_actions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('node');
    $this->installConfig([
      'system',
      'field',
      'file',
      'image',
      'media',
      'node',
      'filter',
    ]);
    $this->installSchema('file', 'file_usage');

    // Create an image media type with its source field.
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

    // Create the article content type with body and image fields.
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    if (!FieldStorageConfig::loadByName('node', 'body')) {
      FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'type' => 'text_long',
      ])->save();
    }
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
      ],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Image',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'image' => 'image',
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Creates an AI automator config entity for the image field.
   *
   * @param string $worker_type
   *   The worker type plugin ID.
   * @param string $id
   *   The automator config entity ID.
   * @param string $rule
   *   The field rule plugin ID. Defaults to image generation; pass a
   *   nonexistent rule ID to simulate a misconfigured automator that would
   *   fatally error if it were ever processed.
   *
   * @return \Drupal\ai_automators\Entity\AiAutomator
   *   The created automator config entity.
   */
  protected function createAutomatorConfig(string $worker_type, string $id = 'node.article.field_image.default', string $rule = 'llm_media_image_generation') {
    $automator = \Drupal::entityTypeManager()
      ->getStorage('ai_automator')
      ->create([
        'id' => $id,
        'label' => 'Image Generation',
        'rule' => $rule,
        'input_mode' => 'base',
        'weight' => 100,
        'worker_type' => $worker_type,
        'entity_type' => 'node',
        'bundle' => 'article',
        'field_name' => 'field_image',
        'edit_mode' => FALSE,
        'base_field' => 'body',
        'prompt' => '{{ context }}',
        'token' => '',
        'plugin_config' => [
          'automator_enabled' => 1,
          'automator_rule' => $rule,
          'automator_mode' => 'base',
          'automator_base_field' => 'body',
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
    return $automator;
  }

  /**
   * Creates an unsaved test node with body text.
   *
   * @return \Drupal\node\Entity\Node
   *   The unsaved node.
   */
  protected function createTestNode(): Node {
    return Node::create([
      'type' => 'article',
      'title' => 'Test Article',
      'body' => [['value' => 'A calm morning by a lake with mountains']],
    ]);
  }

  /**
   * Asserts that a media entity was created with an image file.
   *
   * @param int $media_id
   *   The media entity ID to verify.
   */
  protected function assertMediaImageCreated(int $media_id): void {
    $media = \Drupal::entityTypeManager()->getStorage('media')->load($media_id);
    $this->assertNotNull($media, 'The referenced media entity should exist.');
    $this->assertEquals('image', $media->bundle());

    $source_field = $media->getSource()->getSourceFieldDefinition($media->get('bundle')->entity);
    $this->assertFalse($media->get($source_field->getName())->isEmpty(), 'The media entity should have an image file.');

    $file_id = $media->get($source_field->getName())->target_id;
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($file_id);
    $this->assertNotNull($file, 'The file entity should exist.');
  }

  /**
   * Tests image generation via field widget processing path.
   *
   * Simulates the FWA button click: the node is saved first (presave hook
   * skips FieldWidgetProcessing), then saveEntity() is called with
   * $isAutomated = FALSE — the same call as populateAutomatorValues().
   */
  public function testFieldWidgetProcessingGeneratesImage(): void {
    $this->createAutomatorConfig('field_widget_actions');
    $node = $this->createTestNode();
    // Save the node. The presave hook runs but skips the FieldWidgetProcessing
    // worker (it implements AiAutomatorDirectProcessInterface).
    $node->save();
    $this->assertTrue($node->get('field_image')->isEmpty());

    // Simulate the FWA button click path: saveEntity with isAutomated=FALSE.
    /** @var \Drupal\ai_automators\AiAutomatorEntityModifier $entity_modifier */
    $entity_modifier = \Drupal::service('ai_automator.entity_modifier');
    $result = $entity_modifier->saveEntity($node, FALSE, 'field_image', FALSE);

    $this->assertNotNull($result);
    $this->assertFalse($result->get('field_image')->isEmpty(), 'The image field should have a value after automator processing.');

    $media_id = $result->get('field_image')->target_id;
    $this->assertNotEmpty($media_id);
    $this->assertMediaImageCreated($media_id);
  }

  /**
   * Tests that saveEntity() isolates a single automator by ID.
   *
   * Regression test for the case where two AI automators are configured on
   * the same field (e.g. duplicating an automator config with a different ID
   * suffix — not exposed by the default UI, but possible). Without passing
   * $specificAutomatorId, saveEntity() processes every automator configured
   * for the field, so clicking one Field Widget Action button could run a
   * second, unrelated (and here, misconfigured) automator too.
   *
   * The second automator is deliberately given a nonexistent rule ID so it
   * would fatally error if it were ever invoked: AiFieldRules::findRule()
   * returns NULL for an unknown ID, and the caller immediately calls a
   * method on that NULL. Successfully generating an image while passing
   * only the first automator's ID proves the second automator was never
   * touched.
   */
  public function testSaveEntityFiltersBySpecificAutomatorId(): void {
    $automatorA = $this->createAutomatorConfig('field_widget_actions', 'node.article.field_image.default');
    $this->createAutomatorConfig('field_widget_actions', 'node.article.field_image.broken', 'nonexistent_rule_xyz');

    $node = $this->createTestNode();
    $node->save();
    $this->assertTrue($node->get('field_image')->isEmpty());

    /** @var \Drupal\ai_automators\AiAutomatorEntityModifier $entity_modifier */
    $entity_modifier = \Drupal::service('ai_automator.entity_modifier');
    // Passing the specific automator ID must isolate automator A and never
    // invoke the broken automator B, even though both target field_image.
    $result = $entity_modifier->saveEntity($node, FALSE, 'field_image', FALSE, $automatorA->id());

    $this->assertNotNull($result);
    $this->assertFalse($result->get('field_image')->isEmpty(), 'The image field should have a value after processing the specifically targeted automator.');

    $media_id = $result->get('field_image')->target_id;
    $this->assertNotEmpty($media_id);
    $this->assertMediaImageCreated($media_id);
  }

  /**
   * Tests that saveEntity() returns NULL for an unmatched automator ID.
   *
   * The automator ID does not match any automator configured for the field.
   */
  public function testSaveEntityReturnsNullForUnmatchedAutomatorId(): void {
    $this->createAutomatorConfig('field_widget_actions');
    $node = $this->createTestNode();
    $node->save();

    /** @var \Drupal\ai_automators\AiAutomatorEntityModifier $entity_modifier */
    $entity_modifier = \Drupal::service('ai_automator.entity_modifier');
    $result = $entity_modifier->saveEntity($node, FALSE, 'field_image', FALSE, 'node.article.field_image.does_not_exist');

    $this->assertNull($result);
  }

  /**
   * Tests image generation via direct save processing on entity save.
   *
   * The DirectSaveProcessing worker runs during entity presave, so the image
   * should be generated automatically when the node is saved.
   */
  public function testDirectSaveProcessingGeneratesImageOnSave(): void {
    $this->createAutomatorConfig('direct');
    $node = $this->createTestNode();
    // The presave hook triggers the automator with DirectSaveProcessing.
    $node->save();

    $this->assertFalse($node->get('field_image')->isEmpty(), 'The image field should have a value after entity save.');

    $media_id = $node->get('field_image')->target_id;
    $this->assertNotEmpty($media_id);
    $this->assertMediaImageCreated($media_id);
  }

}
