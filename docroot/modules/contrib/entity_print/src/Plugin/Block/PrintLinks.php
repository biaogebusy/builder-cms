<?php

namespace Drupal\entity_print\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\entity_print\Plugin\ExportTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with a link to print entities.
 *
 * @Block(
 *   id = "print_links",
 *   admin_label = @Translation("Print Links"),
 *   category = @Translation("Entity Print"),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:node")
 *   },
 * )
 */
class PrintLinks extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The export type manager.
   *
   * @var \Drupal\entity_print\Plugin\ExportTypeManagerInterface
   */
  protected $exportTypeManager;

  /**
   * PrintBlock constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\entity_print\Plugin\ExportTypeManagerInterface $exportTypeManager
   *   The export type manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ExportTypeManagerInterface $exportTypeManager) {
    $this->exportTypeManager = $exportTypeManager;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_print.export_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();
    $configuration['label_display'] = '0';

    foreach ($this->exportTypeManager->getDefinitions() as $plugin_id => $definition) {
      $configuration[$plugin_id . '_enabled'] = FALSE;
      $configuration[$plugin_id . '_link_text'] = $this->t('View @label', ['@label' => $definition['label']]);
    }

    // Enable the PDF link by default.
    $configuration['pdf_enabled'] = TRUE;

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;
    $form['print'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Print Links'),
      '#tree' => TRUE,
    ];

    foreach ($this->exportTypeManager->getDefinitions() as $plugin_id => $definition) {
      $form['print'][$plugin_id . '_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable %export_type Link', ['%export_type' => $definition['label']]),
        '#default_value' => $config[$plugin_id . '_enabled'],
      ];
      $form['print'][$plugin_id . '_link_text'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Link text'),
        '#default_value' => $config[$plugin_id . '_link_text'],
        '#states' => [
          'visible' => [
            ':input[name="settings[print][' . $plugin_id . '_enabled]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $print_config = $form_state->getValue('print');
    foreach ($print_config as $key => $value) {
      $this->configuration[$key] = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->getContextValue('entity');
    $configuration = $this->getConfiguration();
    $route_params = [
      'entity_type' => $entity->getEntityTypeId(),
      // Provide fallback to prevent layout builder errors.
      'entity_id' => $entity->id() ?? 0,
    ];

    $build = ['#type' => 'container'];
    foreach ($this->exportTypeManager->getDefinitions() as $plugin_id => $definition) {
      if (!empty($configuration[$plugin_id . '_enabled'])) {
        $route_params['export_type'] = $plugin_id;
        $build[$plugin_id] = [
          '#type' => 'print_link',
          '#export_type' => $plugin_id,
          '#title' => $configuration[$plugin_id . '_link_text'],
          '#url' => Url::fromRoute('entity_print.view', $route_params),
        ];
      }
    }

    return $build;
  }

}
