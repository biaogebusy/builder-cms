<?php

namespace Drupal\charts_blocks\Plugin\Block;

use Drupal\charts\Element\Chart;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'ChartsBlock' block.
 *
 * @Block(
 *  id = "charts_block",
 *  admin_label = @Translation("Charts block"),
 * )
 */
class ChartsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, UuidInterface $uuidService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->configFactory = $config_factory;
    $this->uuidService = $uuidService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('uuid')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    parent::blockForm($form, $form_state);

    $chart_block_configurations = !empty($this->configuration['chart']) ? $this->configuration['chart'] : [];

    // Merge the charts default settings with this block's configuration.
    $charts_settings = $this->configFactory->get('charts.settings');
    $charts_default_settings = $charts_settings->get('charts_default_settings') ?? [];
    $defaults = NestedArray::mergeDeep($charts_default_settings, $chart_block_configurations);

    $form['chart'] = [
      '#type' => 'details',
      '#title' => $this->t('Chart configurations'),
      '#open' => TRUE,
    ];

    $form['chart']['settings'] = [
      '#type' => 'charts_settings',
      '#used_in' => 'basic_form',
      '#required' => TRUE,
      '#series' => TRUE,
      '#default_value' => $defaults,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['chart'] = $form_state->getValue(['chart', 'settings']);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $chart_settings = !empty($this->configuration['chart']) ? $this->configuration['chart'] : [];

    // Creates a UUID for the chart ID.
    $chart_id = 'charts_block__' . $this->configuration['id'];
    $id = 'chart-' . $this->uuidService->generate();
    $build = Chart::buildElement($chart_settings, $chart_id);
    $build['#id'] = $id;
    $build['#chart_id'] = $chart_id;

    return $build;
  }

}
