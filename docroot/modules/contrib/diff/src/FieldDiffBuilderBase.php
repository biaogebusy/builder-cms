<?php

declare(strict_types=1);

namespace Drupal\diff;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for field diff builder plugins.
 */
abstract class FieldDiffBuilderBase extends PluginBase implements FieldDiffBuilderInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a FieldDiffBuilderBase object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DiffEntityParser $entityParser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration += $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('diff.entity_parser'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['show_header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show field title'),
      '#weight' => -5,
      '#default_value' => $this->configuration['show_header'],
    ];
    $form['markdown'] = [
      '#type' => 'select',
      '#title' => $this->t('Markdown callback'),
      '#default_value' => $this->configuration['markdown'],
      '#options' => [
        'drupal_html_to_text' => $this->t('Drupal HTML to Text'),
        'filter_xss' => $this->t('Filter XSS (some tags)'),
        'filter_xss_all' => $this->t('Filter XSS (all tags)'),
      ],
      '#description' => $this->t('These provide ways to clean markup tags to make comparisons easier to read.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // By default an empty validation function is provided.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['show_header'] = $form_state->getValue('show_header');
    $this->configuration['markdown'] = $form_state->getValue('markdown');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'show_header' => 1,
      'markdown' => 'drupal_html_to_text',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldStorageDefinitionInterface $field_definition): bool {
    return TRUE;
  }

}
