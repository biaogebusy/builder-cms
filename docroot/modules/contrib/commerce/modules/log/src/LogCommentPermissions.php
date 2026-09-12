<?php

namespace Drupal\commerce_log;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LogCommentPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new LogCommentPermissions object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_log\LogTemplateManagerInterface $logTemplateManager
   *   The log template manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LogTemplateManagerInterface $logTemplateManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_log_template')
    );
  }

  /**
   * Builds a list of permissions for entity types that support comments.
   *
   * @return array
   *   The permissions.
   */
  public function buildPermissions() {
    $permissions = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      $entity_type_id = $entity_type->id();
      $log_template_id = "{$entity_type_id}_admin_comment";
      if ($this->logTemplateManager->hasDefinition($log_template_id)) {
        $permissions["add commerce_log {$entity_type_id} admin comment"] = [
          'title' => $this->t('Add admin comments to @label', ['@label' => $entity_type->getSingularLabel()]),
          'description' => $this->t('Provides the ability to add admin comments to @label.', ['@label' => $entity_type->getPluralLabel()]),
          'restrict access' => TRUE,
          'provider' => $entity_type->getProvider(),
        ];
      }
    }
    return $permissions;
  }

}
