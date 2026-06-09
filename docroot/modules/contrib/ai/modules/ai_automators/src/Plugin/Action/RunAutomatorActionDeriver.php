<?php

namespace Drupal\ai_automators\Plugin\Action;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives action plugins from ai_automator configs with action worker type.
 */
class RunAutomatorActionDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $automators = $this->entityTypeManager
      ->getStorage('ai_automator')
      ->loadMultiple();

    /** @var \Drupal\ai_automators\AiAutomatorInterface $automator */
    foreach ($automators as $automator) {
      if ($automator->get('worker_type') !== 'action') {
        continue;
      }
      $this->derivatives[$automator->id()] = [
        'type' => $automator->get('entity_type'),
        'label' => $this->t('AI: @label', ['@label' => $automator->label()]),
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
