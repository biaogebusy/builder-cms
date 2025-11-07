<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form to delete a filter on a channel.
 *
 * @package Drupal\entity_share_server\Form
 */
class FilterDeleteForm extends FilterBaseForm {

  /**
   * The filter id.
   *
   * @var string
   */
  protected $filterId;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $filter_id = $this->getFilterId();

    // Check if the filter exists.
    if (!$this->filterIdExists()) {
      $this->messenger()->addError($this->t('There is no filter with the ID @id in this channel', [
        '@id' => $filter_id,
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
    $channel_filters = $channel->get('channel_filters');

    unset($channel_filters[$this->getFilterId()]);

    $channel->set('channel_filters', $channel_filters);
    $channel->save();

    $form_state->setRedirectUrl($channel->toUrl('edit-form'));
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    if (!$this->filterIdExists()) {
      return [];
    }

    $actions = parent::actions($form, $form_state);

    // Change button label.
    $actions['submit']['#value'] = $this->t('Delete filter');

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
