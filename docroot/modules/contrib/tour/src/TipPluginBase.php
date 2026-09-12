<?php

namespace Drupal\tour;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Defines a base tour item implementation.
 *
 * @see \Drupal\tour\Annotation\Tip
 * @see \Drupal\tour\TipPluginInterface
 * @see \Drupal\tour\TipPluginManager
 * @see plugin_api
 */
abstract class TipPluginBase extends PluginBase implements TipPluginInterface {

  /**
   * The label which is used for render of this tip.
   *
   * @var string
   */
  protected string $label;

  /**
   * Allows tips to take more priority that others.
   *
   * @var string
   */
  protected string $weight;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->get('id');
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->get('label');
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->get('weight');
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    if (!empty($this->configuration[$key])) {
      return $this->configuration[$key];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value): void {
    $this->configuration[$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocation(): ?string {
    $location = $this->get('position');

    // The location values accepted by PopperJS, the library used for
    // positioning the tip.
    assert(in_array(trim($location), [
      'auto',
      'auto-start',
      'auto-end',
      'top',
      'top-start',
      'top-end',
      'bottom',
      'bottom-start',
      'bottom-end',
      'right',
      'right-start',
      'right-end',
      'left',
      'left-start',
      'left-end',
      '',
    ], TRUE), "$location is not a valid Tour Tip position value");

    return $location;
  }

  /**
   * {@inheritdoc}
   */
  public function getSelector(): string {
    return $this->get('selector');
  }

  /**
   * {@inheritdoc}
   */
  public function getBody(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'id' => '',
      'label' => '',
      'weight' => 0,
      'selector' => NULL,
      'position' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $id = $this->get('id');
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('The tip title should match the title of the component the selector field is referencing. For the initial general tip with the empty selector field, use the h1 of the page. Each additional tip without a selector should have a unique descriptive title.'),
      '#required' => TRUE,
      '#default_value' => $this->get('label'),
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $id,
      '#disabled' => !empty($id),
      '#machine_name' => [
        'exists' => '\Drupal\tour\Entity\Tour::load',
      ],
    ];
    $form['plugin'] = [
      '#type' => 'value',
      '#value' => $this->get('plugin'),
    ];
    $form['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#default_value' => $this->get('weight'),
      '#attributes' => [
        'class' => ['tip-order-weight'],
      ],
    ];

    $form['selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Selector'),
      '#default_value' => $this->get('selector'),
      '#description' => $this->t('This can be any selector string or a DOM element (e.g,. .some .selector-path or #some-id). If you don’t specify the element will appear in the middle of the screen.'),
    ];

    $form['position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => [
        'auto' => $this->t('Auto'),
        'auto-start' => $this->t('Auto start'),
        'auto-end' => $this->t('Auto end'),
        'top' => $this->t('Top'),
        'top-start' => $this->t('Top start'),
        'top-end' => $this->t('Top end'),
        'bottom' => $this->t('Bottom'),
        'bottom-start' => $this->t('Bottom start'),
        'bottom-end' => $this->t('Bottom end'),
        'right' => $this->t('Right'),
        'right-start' => $this->t('Right start'),
        'right-end' => $this->t('Right end'),
        'left' => $this->t('Left'),
        'left-start' => $this->t('Left start'),
        'left-end' => $this->t('Left end'),
      ],
      '#default_value' => $this->get('position'),
      '#states' => [
        'visible' => [
          ':input[name="selector"]' => ['!value' => ''],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {}

}
