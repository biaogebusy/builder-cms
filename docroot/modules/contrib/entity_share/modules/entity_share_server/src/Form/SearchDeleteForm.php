<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to delete a search on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class SearchDeleteForm extends SearchBaseForm {

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
    $search_id = $this->getsearchId();

    // Check if the search exists.
    if (!$this->searchIdExists()) {
      $this->messenger()->addError($this->t('There is no search with the ID @id in this channel', [
        '@id' => $search_id,
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
    $channel_searches = $channel->get('channel_searches');

    unset($channel_searches[$this->getsearchId()]);

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

    $actions = parent::actions($form, $form_state);

    // Change button label.
    $actions['submit']['#value'] = $this->t('Delete search');

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
