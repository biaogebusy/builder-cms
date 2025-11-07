<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for group form.
 *
 * @package Drupal\entity_share_server\Form
 */
class GroupBaseForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    unset($actions['delete']);
    return $actions;
  }

  /**
   * Helper function to get the conjunction options.
   *
   * @return array
   *   An array of options.
   */
  protected function getConjunctionOptions() {
    return [
      'AND' => $this->t('And'),
      'OR' => $this->t('Or'),
    ];
  }

  /**
   * Helper function to get the conjunction options.
   *
   * @param string $group_id
   *   A group id to exclude. To avoid putting a group into itself.
   *
   * @return array
   *   An array of options.
   */
  protected function getGroupOptions($group_id = '') {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_groups = $channel->get('channel_groups');
    if (is_null($channel_groups)) {
      $channel_groups = [];
    }
    $member_options = array_keys($channel_groups);

    $options = array_combine($member_options, $member_options);

    if (isset($options[$group_id])) {
      unset($options[$group_id]);
    }

    return $options;
  }

  /**
   * Check to see if a group already exists with the specified name.
   *
   * @param string $name
   *   The machine name to check for.
   *
   * @return bool
   *   True if it already exists.
   */
  public function groupExists($name) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_groups = $channel->get('channel_groups');

    if (is_null($channel_groups)) {
      return FALSE;
    }

    if (isset($channel_groups[$name])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Retrieves the group that is being edited.
   *
   * @return string
   *   The group id.
   */
  protected function getgroupId() {
    if (!isset($this->groupId)) {
      $this->groupId = $this->getRequest()->attributes->get('group');
    }

    return $this->groupId;
  }

  /**
   * Check if the group exists.
   *
   * @return bool
   *   True if the group exists. FALSE otherwise.
   */
  protected function groupIdExists() {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_groups = $channel->get('channel_groups');
    $group_id = $this->getgroupId();

    $group_exists = FALSE;
    if (isset($channel_groups[$group_id])) {
      $group_exists = TRUE;
    }

    return $group_exists;
  }

}
