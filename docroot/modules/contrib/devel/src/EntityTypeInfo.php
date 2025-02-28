<?php

namespace Drupal\devel;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manipulates entity type information.
 *
 * This class contains primarily bridged hooks for compile-time or
 * cache-clear-time hooks. Runtime hooks should be placed in EntityOperations.
 */
class EntityTypeInfo implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * EntityTypeInfo constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Adds devel links to appropriate entity types.
   *
   * This is an alter hook bridge.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
   *   The master entity type list to alter.
   *
   * @see hook_entity_type_alter()
   */
  public function entityTypeAlter(array &$entity_types) {
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (($entity_type->getFormClass('default') || $entity_type->getFormClass('edit')) && $entity_type->hasLinkTemplate('edit-form')) {
        // We use edit-form template to extract and set additional parameters
        // dynamically.
        $entity_link = $entity_type->getLinkTemplate('edit-form');
        $this->setEntityTypeLinkTemplate($entity_type, $entity_link, 'devel-load', "/devel/$entity_type_id", $entity_link);
      }
      if ($entity_type->hasViewBuilderClass() && $entity_type->hasLinkTemplate('canonical')) {
        // We use canonical template to extract and set additional parameters
        // dynamically.
        $entity_link = $entity_type->getLinkTemplate('canonical');
        $this->setEntityTypeLinkTemplate($entity_type, $entity_link, 'devel-render', "/devel/render/$entity_type_id", 'canonical');
      }
      if ($entity_type->hasLinkTemplate('devel-render') || $entity_type->hasLinkTemplate('devel-load')) {
        // We use canonical or edit-form template to extract and set additional
        // parameters dynamically.
        $entity_link = $entity_type->getLinkTemplate('edit-form');
        if (empty($entity_link)) {
          $entity_link = $entity_type->getLinkTemplate('canonical');
        }
        $this->setEntityTypeLinkTemplate($entity_type, $entity_link, 'devel-definition', "/devel/definition/$entity_type_id");
      }
    }

  }

  /**
   * Sets entity type link template.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type.
   * @param string $entity_link
   *   Entity link.
   * @param string $devel_link_key
   *   Devel link key.
   * @param string $base_path
   *   Base path for devel link key.
   */
  protected function setEntityTypeLinkTemplate(EntityTypeInterface $entity_type, $entity_link, $devel_link_key, $base_path) {
    // Extract all route parameters from the given template and set them to
    // the current template.
    // Some entity templates can contain not only entity id,
    // for example /user/{user}/documents/{document}
    // /group/{group}/content/{group_content}
    // We use canonical or edit-form templates to get these parameters and set
    // them for devel entity link templates.
    $path_parts = $this->getPathParts($entity_link);
    $entity_type->setLinkTemplate($devel_link_key, $base_path . $path_parts);
  }

  /**
   * Get path parts.
   *
   * @param string $entity_path
   *   Entity path.
   *
   * @return string
   *   Path parts.
   */
  protected function getPathParts($entity_path) {
    $path = '';
    if (preg_match_all('/{\w*}/', $entity_path, $matches)) {
      foreach ($matches[0] as $match) {
        $path .= "/$match";
      }
    }
    return $path;
  }

  /**
   * Adds devel operations on entity that supports it.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity on which to define an operation.
   *
   * @return array
   *   An array of operation definitions.
   *
   * @see hook_entity_operation()
   */
  public function entityOperation(EntityInterface $entity) {
    $operations = [];
    if ($this->currentUser->hasPermission('access devel information')) {
      if ($entity->hasLinkTemplate('devel-load')) {
        $operations['devel'] = [
          'title' => $this->t('Devel'),
          'weight' => 100,
          'url' => $entity->toUrl('devel-load'),
        ];
      }
      elseif ($entity->hasLinkTemplate('devel-render')) {
        $operations['devel'] = [
          'title' => $this->t('Devel'),
          'weight' => 100,
          'url' => $entity->toUrl('devel-render'),
        ];
      }
    }
    return $operations;
  }

}
