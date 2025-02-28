<?php

namespace Drupal\entity_print\Plugin\Action;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_print\Plugin\ExportTypeManagerInterface;
use Drupal\entity_print\PrintBuilderInterface;
use Drupal\entity_print\PrintEngineException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the Printed entity.
 *
 * @Action(
 *   id = "entity_print_download_action",
 *   label = @Translation("Print"),
 *   type = "node"
 * )
 *
 * @todo support multiple entity types once core is fixed.
 * @see https://www.drupal.org/node/2011038
 */
class PrintDownload extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * Access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The Print builder service.
   *
   * @var \Drupal\entity_print\PrintBuilderInterface
   */
  protected $printBuilder;

  /**
   * The Entity Print plugin manager.
   *
   * @var \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface
   */
  protected $entityPrintPluginManager;

  /**
   * The export type manager.
   *
   * @var \Drupal\entity_print\Plugin\ExportTypeManagerInterface
   */
  protected $exportTypeManager;

  /**
   * The Print engine implementation.
   *
   * @var \Drupal\entity_print\Plugin\PrintEngineInterface
   */
  protected $printEngine;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccessManagerInterface $access_manager, PrintBuilderInterface $print_builder, PluginManagerInterface $entity_print_plugin_manager, ExportTypeManagerInterface $export_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->accessManager = $access_manager;
    $this->printBuilder = $print_builder;
    $this->entityPrintPluginManager = $entity_print_plugin_manager;
    $this->exportTypeManager = $export_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('access_manager'),
      $container->get('entity_print.print_builder'),
      $container->get('plugin.manager.entity_print.print_engine'),
      $container->get('plugin.manager.entity_print.export_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\node\NodeInterface $object */
    $route_params = [
      'export_type' => $this->configuration['export_type'],
      'entity_id' => $object->id(),
      'entity_type' => $object->getEntityTypeId(),
    ];
    return $this->accessManager->checkNamedRoute('entity_print.view', $route_params, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $this->executeMultiple([$entity]);
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    try {
      (new StreamedResponse(function () use ($entities) {
        $this->printBuilder->deliverPrintable($entities, $this->entityPrintPluginManager->createSelectedInstance($this->configuration['export_type']), TRUE);
      }))->send();
    }
    catch (PrintEngineException $e) {
      $this->messenger()->addError(new FormattableMarkup(Xss::filter($e->getMessage()), []));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['export_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Export type'),
      '#options' => $this->exportTypeManager->getFormOptions(),
      '#required' => TRUE,
      '#default_value' => !empty($this->configuration['export_type']) ? $this->configuration['export_type'] : NULL,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['export_type'] = $form_state->getValue('export_type');
  }

}
