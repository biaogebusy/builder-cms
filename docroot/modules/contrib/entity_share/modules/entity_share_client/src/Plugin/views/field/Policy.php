<?php

namespace Drupal\entity_share_client\Plugin\views\field;

use Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views field plugin for the import policy.
 */
#[ViewsField('entity_share_client_policy')]
class Policy extends FieldPluginBase {

  /**
   * The import policy plugin manager.
   *
   * @var \Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager
   */
  protected $importPolicyPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_share_client_policy'),
    );
  }

  /**
   * Creates a Policy instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_share_client\ImportPolicy\ImportPolicyPluginManager $import_policy_plugin_manager
   *   The import policy plugin manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ImportPolicyPluginManager $import_policy_plugin_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->importPolicyPluginManager = $import_policy_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    $policy_definition = $this->importPolicyPluginManager->getDefinition($value);

    return $this->sanitizeValue($policy_definition['label']);
  }

}
