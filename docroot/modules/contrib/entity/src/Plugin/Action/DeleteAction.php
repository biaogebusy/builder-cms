<?php

namespace Drupal\entity\Plugin\Action;

use Drupal\Core\Action\Plugin\Action\DeleteAction as CoreDeleteAction;

@trigger_error('\Drupal\entity\Plugin\Action\DeleteAction has been deprecated in favor of \Drupal\Core\Action\Plugin\Action\DeleteAction. Use that instead.');

/**
 * Redirects to an entity deletion form.
 *
 * @deprecated Use "entity:delete_action" instead.
 *
 * @Action(
 *   id = "entity_delete_action",
 *   label = @Translation("Delete entity"),
 *   deriver = "Drupal\entity\Plugin\Action\Derivative\DeleteActionDeriver",
 * )
 */
class DeleteAction extends CoreDeleteAction {}
