<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class to sort form.
 *
 * @package Drupal\entity_share_server\Form
 */
class SortBaseForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    unset($actions['delete']);
    return $actions;
  }

  /**
   * Helper function to get the operator options.
   *
   * @return array
   *   An array of options.
   */
  protected function getDirectionOptions() {
    return [
      'ASC' => $this->t('Ascending'),
      'DESC' => $this->t('Descending'),
    ];
  }

  /**
   * Check to see if a sort already exists with the specified name.
   *
   * @param string $name
   *   The machine name to check for.
   *
   * @return bool
   *   True if it already exists.
   */
  public function sortExists($name) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_sorts = $channel->get('channel_sorts');

    if (is_null($channel_sorts)) {
      return FALSE;
    }

    if (isset($channel_sorts[$name])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Retrieves the sort that is being edited.
   *
   * @return string
   *   The sort id.
   */
  protected function getsortId() {
    if (!isset($this->sortId)) {
      $this->sortId = $this->getRequest()->attributes->get('sort');
    }

    return $this->sortId;
  }

  /**
   * Check if the sort exists.
   *
   * @return bool
   *   True if the sort exists. FALSE otherwise.
   */
  protected function sortIdExists() {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_sorts = $channel->get('channel_sorts');
    $sort_id = $this->getsortId();

    $sort_exists = FALSE;
    if (isset($channel_sorts[$sort_id])) {
      $sort_exists = TRUE;
    }

    return $sort_exists;
  }

}
