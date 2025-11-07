<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to add a search on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class SearchAddForm extends SearchBaseForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('Enter the machine name of the field / property you want to be able to search in, on the client website. You can reference field / property of a referenced entity. Example: uid.name for the name of the author.'),
      '#required' => TRUE,
    ];

    $form['search_id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#machine_name' => [
        'source' => ['path'],
        'exists' => [$this, 'searchExists'],
      ],
    ];

    $form['search_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
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
    $channel_searches = $channel->get('channel_searches');

    if (is_null($channel_searches)) {
      $channel_searches = [];
    }

    $channel_searches[$form_state->getValue('search_id')] = [
      'path' => $form_state->getValue('path'),
      'label' => $form_state->getValue('search_label'),
    ];
    $channel->set('channel_searches', $channel_searches);
    $channel->save();

    $form_state->setRedirectUrl($channel->toUrl('edit-form'));
  }

}
