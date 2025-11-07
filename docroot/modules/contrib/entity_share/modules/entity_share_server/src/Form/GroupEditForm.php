<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to edit a group on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class GroupEditForm extends GroupBaseForm {

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
    // Check if the group exists.
    if (!$this->groupIdExists()) {
      $this->messenger()->addError($this->t('There is no group with the ID @id in this channel', [
        '@id' => $this->getgroupId(),
      ]));

      return [];
    }
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_groups = $channel->get('channel_groups');
    $group_id = $this->getgroupId();
    if (is_null($channel_groups)) {
      $channel_groups = [];
    }

    $form['group_id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#default_value' => $group_id,
      '#machine_name' => [
        'exists' => [$this, 'groupExists'],
      ],
      '#disabled' => TRUE,
    ];

    $form['conjunction'] = [
      '#type' => 'select',
      '#title' => $this->t('Conjunction'),
      '#options' => $this->getConjunctionOptions(),
      '#default_value' => $channel_groups[$group_id]['conjunction'],
      '#required' => TRUE,
    ];

    $form['memberof'] = [
      '#type' => 'select',
      '#title' => $this->t('Parent group'),
      '#options' => $this->getGroupOptions($group_id),
      '#empty_option' => $this->t('Select a group'),
      '#default_value' => isset($channel_groups[$group_id]['memberof']) ? $channel_groups[$group_id]['memberof'] : '',
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

    $channel_groups[$form_state->getValue('group_id')] = [
      'conjunction' => $form_state->getValue('conjunction'),
    ];

    if (!empty($form_state->getValue('memberof'))) {
      $channel_groups[$form_state->getValue('group_id')]['memberof'] = $form_state->getValue('memberof');
    }
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

    return parent::actions($form, $form_state);
  }

}
