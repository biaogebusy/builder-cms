<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to delete a sort on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class SortDeleteForm extends SortBaseForm {

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
    $sort_id = $this->getsortId();

    // Check if the sort exists.
    if (!$this->sortIdExists()) {
      $this->messenger()->addError($this->t('There is no sort with the ID @id in this channel', [
        '@id' => $sort_id,
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
    $channel_sorts = $channel->get('channel_sorts');

    unset($channel_sorts[$this->getsortId()]);

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

    $actions = parent::actions($form, $form_state);

    // Change button label.
    $actions['submit']['#value'] = $this->t('Delete sort');

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
