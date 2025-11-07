<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to delete a group on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class GroupDeleteForm extends GroupBaseForm {

  /**
   * The group id.
   *
   * @var string
   */
  protected $groupId;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $group_id = $this->getgroupId();

    // Check if the group exists.
    if (!$this->groupIdExists()) {
      $this->messenger()->addError($this->t('There is no group with the ID @id in this channel', [
        '@id' => $group_id,
      ]));

      return [];
    }
    $form = parent::form($form, $form_state);

    $form['description'] = [
      '#markup' => $this->t('This action cannot be undone.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_groups = $channel->get('channel_groups');

    unset($channel_groups[$this->getgroupId()]);

    $channel->set('channel_groups', $channel_groups);
    $channel->save();

    $form_state->setRedirectUrl($channel->toUrl('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    if (!$this->groupIdExists()) {
      return [];
    }

    $actions = parent::actions($form, $form_state);

    // Change button label.
    $actions['submit']['#value'] = $this->t('Delete group');

    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    // Add cancel link.
    $actions['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $channel->toUrl('edit-form'),
      '#attributes' => ['class' => ['button']],
    ];

    return $actions;
  }

}
