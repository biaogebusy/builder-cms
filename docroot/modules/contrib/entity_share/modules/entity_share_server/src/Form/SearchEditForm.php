<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to edit a search on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class SearchEditForm extends SearchBaseForm {

  /**
   * The search id.
   *
   * @var string
   */
  protected $searchId;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Check if the search exists.
    if (!$this->searchIdExists()) {
      $this->messenger()->addError($this->t('There is no search with the ID @id in this channel', [
        '@id' => $this->getsearchId(),
      ]));

      return [];
    }
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_searches = $channel->get('channel_searches');
    $search_id = $this->getsearchId();

    $form['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('Enter the machine name of the field / property you want to be able to search in, on the client website. You can reference field / property of a referenced entity. Example: uid.name for the name of the author.'),
      '#required' => TRUE,
      '#default_value' => $channel_searches[$search_id]['path'],
    ];

    $form['search_id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('ID'),
      '#default_value' => $search_id,
      '#machine_name' => [
        'source' => ['path'],
        'exists' => [$this, 'searchExists'],
      ],
      '#disabled' => TRUE,
    ];

    $form['search_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $channel_searches[$search_id]['label'],
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

    $channel_searches[$form_state->getValue('search_id')] = [
      'path' => $form_state->getValue('path'),
      'label' => $form_state->getValue('search_label'),
    ];
    $channel->set('channel_searches', $channel_searches);
    $channel->save();

    $form_state->setRedirectUrl($channel->toUrl('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    if (!$this->searchIdExists()) {
      return [];
    }

    return parent::actions($form, $form_state);
  }

}
