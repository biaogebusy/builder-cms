<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\Action;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Update the policy of the selected entity import statuses.
 *
 * @Action(
 *   id = "entity_share_client_update_policy",
 *   label = @Translation("Update policy"),
 *   type = "entity_import_status",
 *   confirm = TRUE,
 * )
 */
class UpdatePolicyAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, PluginFormInterface {

  /**
   * The import policies manager.
   *
   * @var \Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager
   */
  protected $policiesManager;

  /**
   * The available policies.
   *
   * @var array
   */
  protected $policies;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin Id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager $policiesManager
   *   The import policies manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ImportPolicyPluginManager $policiesManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->policiesManager = $policiesManager;
    $this->policies = $this->policiesManager->getDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_share_client_policy')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\entity_share_client\Entity\EntityImportStatusInterface $entity */
    $entity->setPolicy($this->configuration['selected_policy']);
    $entity->save();

    return $this->t('The policy "@policy" has been set on the selected entity import statuses.', [
      '@policy' => $this->policies[$this->configuration['selected_policy']]['label'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['selected_policy'] = [
      '#type' => 'select',
      '#title' => $this->t('Policy'),
      '#description' => $this->t('Select the policy to apply'),
      '#options' => $this->policiesManager->getOptionsList(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\entity_share_client\Entity\EntityImportStatusInterface $object */
    return $object->access('update', $account, $return_as_object);
  }

}
