<?php

namespace Drupal\consumers\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A menu link to Consumers collection page.
 */
class ConsumersCollectionLink extends MenuLinkDefault {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->entityTypeManager->getDefinition('consumer')->getCollectionLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Register and configure the decoupled @label to your API.', [
      '@label' => $this->entityTypeManager->getDefinition('consumer')->getPluralLabel(),
    ]);
  }

}
