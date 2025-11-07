<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to add a group on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class GroupAddForm extends GroupBaseForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['group_id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#machine_name' => [
        'exists' => [$this, 'groupExists'],
      ],
    ];

    $form['conjunction'] = [
      '#type' => 'select',
      '#title' => $this->t('Conjunction'),
      '#options' => $this->getConjunctionOptions(),
      '#default_value' => 'AND',
      '#required' => TRUE,
    ];

    $form['memberof'] = [
      '#type' => 'select',
      '#title' => $this->t('Parent group'),
      '#options' => $this->getGroupOptions(),
      '#empty_option' => $this->t('Select a group'),
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

    if (is_null($channel_groups)) {
      $channel_groups = [];
    }
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

}
