<?php

namespace Drupal\advancedqueue\Plugin\views\field;

use Drupal\advancedqueue\Job;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to render the job state.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("advancedqueue_job_state")
 */
class JobState extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    return [
      'icon' => ['default' => FALSE],
    ] + parent::defineOptions();
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['icon'] = [
      '#title' => $this->t('Use an icon'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['icon'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $state = $this->getValue($values);
    $state_options = self::getOptions();
    $label = $state_options[$state] ?? $state;

    if ($this->options['icon']) {
      return [
        '#theme' => 'advancedqueue_state_icon',
        '#state' => [
          'state' => $state,
          'label' => $label,
        ],
      ];
    }
    else {
      return $label;
    }
  }

  /**
   * Gets the available state options.
   *
   * @return array
   *   The state options.
   */
  public static function getOptions() {
    return [
      Job::STATE_QUEUED => new TranslatableMarkup('Queued'),
      Job::STATE_PROCESSING => new TranslatableMarkup('Processing'),
      Job::STATE_SUCCESS => new TranslatableMarkup('Success'),
      Job::STATE_FAILURE => new TranslatableMarkup('Failure'),
    ];
  }

}
