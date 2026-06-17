<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder_st\Functional\Rest;

use Drupal\FunctionalTests\Rest\EntityViewDisplayResourceTestBase;
use Drupal\layout_builder_st\Plugin\SectionStorage\OverridesSectionStorage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Provides a base class for testing LayoutBuilderEntityViewDisplay resources.
 */
#[Group('layout_builder_st')]
#[Group('rest')]
#[RunTestsInSeparateProcesses]
abstract class LayoutBuilderEntityViewDisplayResourceTestBase extends EntityViewDisplayResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'layout_builder_st',
  ];

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $entity */
    $entity = parent::createEntity();
    $entity
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();
    $this->assertCount(1, $entity->getThirdPartySetting('layout_builder', 'sections'));
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $expected = parent::getExpectedNormalizedEntity();
    array_unshift($expected['dependencies']['module'], 'layout_builder');
    // @todo remove this logic after Drupal 10 support is sunset.
    if (version_compare(\Drupal::VERSION, '10.9', '>')) {
      unset($expected['content']['links']);
    }
    $expected['hidden'][OverridesSectionStorage::FIELD_NAME] = TRUE;
    $expected['hidden'][OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME] = TRUE;
    if (version_compare(\Drupal::VERSION, '10.9', '>')) {
      $expected['hidden']['links'] = TRUE;
    }
    $expected['third_party_settings']['layout_builder'] = [
      'enabled' => TRUE,
      'allow_custom' => TRUE,
    ];
    return $expected;
  }

}
