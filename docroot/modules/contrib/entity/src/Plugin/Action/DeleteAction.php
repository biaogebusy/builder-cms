<?php

namespace Drupal\entity\Plugin\Action;

use Drupal\Core\Action\Plugin\Action\DeleteAction as CoreDeleteAction;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Redirects to an entity deletion form.
 *
 * @deprecated in entity:8.x-1.7 and is removed from entity:2.0.0. Use "entity:delete_action" instead.
 * @see https://www.drupal.org/node/2997467
 *
 * @Action(
 *   id = "entity_delete_action",
 *   label = @Translation("Delete entity"),
 *   deriver = "Drupal\entity\Plugin\Action\Derivative\DeleteActionDeriver",
 * )
 */
class DeleteAction extends CoreDeleteAction {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user) {
    @trigger_error('\Drupal\entity\Plugin\Action\DeleteAction is deprecated in entity:8.x-1.6 and is removed from entity:2.0.0. Use \Drupal\Core\Action\Plugin\Action\DeleteAction instead. See https://www.drupal.org/node/2997467', E_USER_DEPRECATED);
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $temp_store_factory, $current_user);
  }

}
