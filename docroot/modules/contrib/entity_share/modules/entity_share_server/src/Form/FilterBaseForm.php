<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for filter form.
 *
 * @package Drupal\entity_share_server\Form
 */
class FilterBaseForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    unset($actions['delete']);
    return $actions;
  }

  /**
   * Helper function to get the conjunction options.
   *
   * @return array
   *   An array of options.
   */
  protected function getGroupOptions() {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_groups = $channel->get('channel_groups');
    if (is_null($channel_groups)) {
      $channel_groups = [];
    }
    $member_options = array_keys($channel_groups);

    return array_combine($member_options, $member_options);
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Subform.
   */
  public static function buildAjaxValueElement(array $form, FormStateInterface $form_state) {
    // We just need to return the relevant part of the form here.
    return $form['value_wrapper'];
  }

  /**
   * Ajax callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Subform.
   */
  public function addRemoveCallback(array &$form, FormStateInterface $form_state) {
    return $form['value_wrapper'];
  }

  /**
   * Submit handler for the "add a value" button.
   *
   * Increments the max counter and causes a rebuild.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function addOneValue(array &$form, FormStateInterface $form_state) {
    $number_of_values = $form_state->get('number_of_values');
    $number_of_values++;
    $form_state->set('number_of_values', $number_of_values);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove a value" button.
   *
   * Decrements the max counter and causes a form rebuild.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function removeOneValue(array &$form, FormStateInterface $form_state) {
    $number_of_values = $form_state->get('number_of_values');
    if ($number_of_values > 1) {
      $number_of_values--;
      $form_state->set('number_of_values', $number_of_values);
    }
    $form_state->setRebuild();
  }

  /**
   * Check to see if a filter already exists with the specified name.
   *
   * @param string $name
   *   The machine name to check for.
   *
   * @return bool
   *   True if it already exists.
   */
  public function filterExists($name) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_filters = $channel->get('channel_filters');

    if (is_null($channel_filters)) {
      return FALSE;
    }

    if (isset($channel_filters[$name])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Retrieves the filter that is being edited.
   *
   * @return string
   *   The filter id.
   */
  protected function getFilterId() {
    if (!isset($this->filterId)) {
      $this->filterId = $this->getRequest()->attributes->get('filter');
    }

    return $this->filterId;
  }

  /**
   * Check if the filter exists.
   *
   * @return bool
   *   True if the filter exists. FALSE otherwise.
   */
  protected function filterIdExists() {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_filters = $channel->get('channel_filters');
    $filter_id = $this->getFilterId();

    $filter_exists = FALSE;
    if (isset($channel_filters[$filter_id])) {
      $filter_exists = TRUE;
    }

    return $filter_exists;
  }

}
