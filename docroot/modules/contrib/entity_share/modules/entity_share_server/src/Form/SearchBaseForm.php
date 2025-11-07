<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for search form.
 *
 * @package Drupal\entity_share_server\Form
 */
class SearchBaseForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    unset($actions['delete']);
    return $actions;
  }

  /**
   * Check to see if a search already exists with the specified name.
   *
   * @param string $name
   *   The machine name to check for.
   *
   * @return bool
   *   True if it already exists.
   */
  public function searchExists($name) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_searches = $channel->get('channel_searches');

    if (is_null($channel_searches)) {
      return FALSE;
    }

    if (isset($channel_searches[$name])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Retrieves the search that is being edited.
   *
   * @return string
   *   The search id.
   */
  protected function getsearchId() {
    if (!isset($this->searchId)) {
      $this->searchId = $this->getRequest()->attributes->get('search');
    }

    return $this->searchId;
  }

  /**
   * Check if the search exists.
   *
   * @return bool
   *   True if the search exists. FALSE otherwise.
   */
  protected function searchIdExists() {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_searches = $channel->get('channel_searches');
    $search_id = $this->getsearchId();

    $search_exists = FALSE;
    if (isset($channel_searches[$search_id])) {
      $search_exists = TRUE;
    }

    return $search_exists;
  }

}
