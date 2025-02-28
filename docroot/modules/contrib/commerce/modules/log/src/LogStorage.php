<?php

namespace Drupal\commerce_log;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LogStorage extends CommerceContentEntityStorage implements LogStorageInterface {

  /**
   * The log template manager.
   *
   * @var \Drupal\commerce_log\LogTemplateManagerInterface
   */
  protected $logTemplateManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->logTemplateManager = $container->get('plugin.manager.commerce_log_template');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function generate(ContentEntityInterface $source, $template_id, array $params = []) {
    $template_plugin = $this->logTemplateManager->getDefinition($template_id);
    $log = $this->create([
      'category_id' => $template_plugin['category'],
      'template_id' => $template_id,
      'source_entity_id' => $source->id(),
      'source_entity_type' => $source->getEntityTypeId(),
      'params' => $params,
    ]);
    return $log;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleByEntity(ContentEntityInterface $entity) {
    return $this->loadByProperties([
      'source_entity_id' => $entity->id(),
      'source_entity_type' => $entity->getEntityTypeId(),
    ]);
  }

}
