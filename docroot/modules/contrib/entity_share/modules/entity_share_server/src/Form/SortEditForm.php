<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to edit a sort on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class SortEditForm extends SortBaseForm {

  /**
   * The sort id.
   *
   * @var string
   */
  protected $sortId;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Check if the sort exists.
    if (!$this->sortIdExists()) {
      $this->messenger()->addError($this->t('There is no sort with the ID @id in this channel', [
        '@id' => $this->getsortId(),
      ]));

      return [];
    }
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_sorts = $channel->get('channel_sorts');
    $sort_id = $this->getsortId();

    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('Enter the machine name of the field / property you want to sort on. You can reference field / property of a referenced entity. Example: uid.name for the name of the author.'),
      '#required' => TRUE,
      '#default_value' => $channel_sorts[$sort_id]['path'],
    ];

    $form['sort_id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#default_value' => $sort_id,
      '#machine_name' => [
        'source' => ['path'],
        'exists' => [$this, 'sortExists'],
      ],
      '#disabled' => TRUE,
    ];

    $form['direction'] = [
      '#type' => 'select',
      '#title' => $this->t('Direction'),
      '#options' => $this->getDirectionOptions(),
      '#default_value' => $channel_sorts[$sort_id]['direction'],
      '#required' => TRUE,
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $channel_sorts[$sort_id]['weight'],
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

    $channel_sorts[$form_state->getValue('sort_id')] = [
      'path' => $form_state->getValue('path'),
      'direction' => $form_state->getValue('direction'),
      'weight' => $form_state->getValue('weight'),
    ];
    $channel->set('channel_sorts', $channel_sorts);
    $channel->save();

    $form_state->setRedirectUrl($channel->toUrl('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    if (!$this->sortIdExists()) {
      return [];
    }

    return parent::actions($form, $form_state);
  }

}
