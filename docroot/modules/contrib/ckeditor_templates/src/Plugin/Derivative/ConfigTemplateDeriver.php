<?php

namespace Drupal\ckeditor_templates\Plugin\Derivative;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a ckeditor_template for every ckeditor_templates config entity.
 *
 * @internal
 *   Plugin derivers are internal.
 */
class ConfigTemplateDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs new ConfigTemplateDeriver.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

    /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->getTemplates() as $ckeditor_template) {
    $derivative = $base_plugin_definition;
      $derivative['ckeditor_template_id'] = $ckeditor_template->id();
      $derivative['label'] = $ckeditor_template->label();
      $derivative['description'] = $ckeditor_template->get('description') ?? '';
      $derivative['weight'] = $ckeditor_template->get('weight');
      $this->derivatives[$ckeditor_template->id()] = $derivative;
    }

    return $this->derivatives;
  }

  /**
   * Loads the CKEditor Templates.
   *
   * @return \Drupal\ckeditor_templates\CKEditorTemplatesInterface[]
   *   A list of CKEditor Template entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getTemplates(): array {
    $storage = $this->entityTypeManager
      ->getStorage('ckeditor_templates');

    $ids = $storage->getQuery()
      ->condition('status', 1)
      ->sort('weight', 'ASC')
      ->execute();

    return $storage->loadMultiple($ids);
  }

}
