<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Entity\AiGuardrailSet;

/**
 * Tests dependency calculation for guardrail set config entities.
 *
 * @group ai
 * @covers \Drupal\ai\Entity\AiGuardrailSet::calculateDependencies
 */
final class AiGuardrailSetDependencyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'key',
    'system',
  ];

  /**
   * Tests config dependencies are saved for referenced guardrails.
   */
  public function testReferencedGuardrailsAreAddedAsDependencies(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    // Create pre_guardrail and post_guardrail.
    $guardrail_storage = $entity_type_manager->getStorage('ai_guardrail');

    $guardrail_storage->create([
      'id' => 'pre_guardrail',
      'label' => 'Pre Guardrail',
      'description' => 'Description for Pre Guardrail',
      'guardrail' => 'regexp_guardrail',
      'guardrail_settings' => [
        'regexp_pattern' => '/(?<!\w)(?:\+|0|00)[1-9][0-9\s().-]{6,20}\d(?!\w)/',
        'violation_message' => 'This message was blocked because it contains a phone number.',
      ],
    ])->save();

    $guardrail_storage->create([
      'id' => 'post_guardrail',
      'label' => 'Post Guardrail',
      'description' => 'Description for Post Guardrail',
      'guardrail' => 'regexp_guardrail',
      'guardrail_settings' => [
        'regexp_pattern' => '/(?<!\w)(?:\+|0|00)[1-9][0-9\s().-]{6,20}\d(?!\w)/',
        'violation_message' => 'This message was blocked because it contains a phone number.',
      ],
    ])->save();

    $guardrail_set_storage = $entity_type_manager->getStorage('ai_guardrail_set');

    $guardrail_set_storage->create([
      'id' => 'guardrail_set_with_refs',
      'label' => 'Guardrail set with references',
      'description' => 'Guardrail set description.',
      'stop_threshold' => 0.8,
      'pre_generate_guardrails' => ['plugin_id' => ['pre_guardrail', 'pre_guardrail', '']],
      'post_generate_guardrails' => ['plugin_id' => ['', 'post_guardrail']],
    ])->save();

    /** @var \Drupal\ai\Entity\AiGuardrailSet|null $guardrail_set */
    $guardrail_set = $guardrail_set_storage->load('guardrail_set_with_refs');
    $this->assertInstanceOf(AiGuardrailSet::class, $guardrail_set);

    $dependencies = $guardrail_set->getDependencies();
    $this->assertArrayHasKey('config', $dependencies);
    $this->assertEqualsCanonicalizing([
      'ai.ai_guardrail.pre_guardrail',
      'ai.ai_guardrail.post_guardrail',
    ], $dependencies['config']);

    $raw_config = $this->config('ai.ai_guardrail_set.guardrail_set_with_refs')->getRawData();
    $this->assertEqualsCanonicalizing([
      'ai.ai_guardrail.pre_guardrail',
      'ai.ai_guardrail.post_guardrail',
    ], $raw_config['dependencies']['config'] ?? []);
  }

}
