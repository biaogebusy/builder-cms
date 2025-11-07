<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to add a sort on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class SortAddForm extends SortBaseForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('Enter the machine name of the field / property you want to sort on. You can reference field / property of a referenced entity. Example: uid.name for the name of the author.'),
      '#required' => TRUE,
    ];

    $form['sort_id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#machine_name' => [
        'source' => ['path'],
        'exists' => [$this, 'sortExists'],
      ],
    ];

    $form['direction'] = [
      '#type' => 'select',
      '#title' => $this->t('Direction'),
      '#options' => $this->getDirectionOptions(),
      '#default_value' => 'ASC',
      '#required' => TRUE,
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => 0,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_sorts = $channel->get('channel_sorts');

    if (is_null($channel_sorts)) {
      $channel_sorts = [];
    }

    $channel_sorts[$form_state->getValue('sort_id')] = [
      'path' => $form_state->getValue('path'),
      'direction' => $form_state->getValue('direction'),
      'weight' => $form_state->getValue('weight'),
    ];
    $channel->set('channel_sorts', $channel_sorts);
    $channel->save();

    $form_state->setRedirectUrl($channel->toUrl('edit-form'));
  }

}
