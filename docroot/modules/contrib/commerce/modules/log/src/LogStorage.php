<?php

namespace Drupal\commerce_log;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce_log\Entity\LogInterface;
use Drupal\commerce_log\Form\LogSettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LogStorage extends CommerceContentEntityStorage implements LogStorageInterface {

  /**
   * The log template manager.
   */
  protected LogTemplateManagerInterface $logTemplateManager;

  /**
   * The config factory service.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->logTemplateManager = $container->get('plugin.manager.commerce_log_template');
    $instance->configFactory = $container->get('config.factory');
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
  public function save(EntityInterface $entity) {
    if (!($entity instanceof LogInterface)) {
      return parent::save($entity);
    }

    // When the list of disabled templates is not configured or the saved
    // template is not in the list continue with the default process.
    $log_settings = $this->configFactory->get(LogSettingsForm::CONFIG_NAME);
    $disabled_templates = $log_settings->get('disabled_templates');
    if (empty($disabled_templates) || !in_array($entity->getTemplateId(), $disabled_templates)) {
      return parent::save($entity);
    }

    return NULL;
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
