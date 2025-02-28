<?php

namespace Drupal\entity_print_views\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\LinkBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to present print links for an entity.
 *
 * @ingroup entity_print
 *
 * @ViewsField("entity_print_link")
 */
class PrintLink extends LinkBase {

  /**
   * The print export type manager.
   *
   * @var \Drupal\entity_print\Plugin\ExportTypeManagerInterface
   */
  protected $exportTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->exportTypeManager = $container->get('plugin.manager.entity_print.export_type');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['open_new_window'] = ['default' => FALSE];
    $options['export_type'] = ['default' => 'pdf'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {
    $entity = $this->getEntity($row);
    if (!empty($this->options['open_new_window'])) {
      $this->options['alter']['target'] = '_blank';
    }

    return Url::fromRoute('entity_print.view', [
      'entity_type' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
      'export_type' => $this->options['export_type'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['export_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Export Type'),
      '#required' => TRUE,
      '#options' => $this->exportTypeManager->getFormOptions(),
      '#default_value' => $this->options['export_type'],
    ];
    $form['open_new_window'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open in new window'),
      '#default_value' => $this->options['open_new_window'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel() {
    return $this->t('View PDF');
  }

}
