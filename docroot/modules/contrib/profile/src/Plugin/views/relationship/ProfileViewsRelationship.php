<?php

namespace Drupal\profile\Plugin\views\relationship;

use Drupal\Core\Form\FormStateInterface;
use Drupal\profile\Entity\ProfileType;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;

/**
 * Provides a views relationship to select profile content by a profile_type.
 *
 * @ViewsRelationship("profile_relationship")
 */
class ProfileViewsRelationship extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['profile_type'] = ['default' => NULL];
    $options['required'] = ['default' => 1];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['admin_label']['#description'] = $this->t('The name of the selected profile type makes a good label.');

    $form['profile_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Profile Type'),
      '#default_value' => $this->options['profile_type'],
      '#required' => TRUE,
    ];
    foreach (ProfileType::loadMultiple() as $profile_id => $profile_type) {
      $form['profile_type']['#options'][$profile_id] = $profile_type->label();
    }

    $form['required']['#description'] .= '<div class="color-warning"><strong>' . $this->t('You must require this relationship to use the Rendered Entity field type for this relationship') . '</strong></div>';
  }

  /**
   * {@inheritdoc}
   */
  public function query() {

    $this->definition['extra'][] = [
      'field' => 'type',
      'value' => $this->options['profile_type'],
    ];

    parent::query();
  }

}
