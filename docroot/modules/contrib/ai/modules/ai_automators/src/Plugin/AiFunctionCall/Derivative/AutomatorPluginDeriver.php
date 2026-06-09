<?php

namespace Drupal\ai_automators\Plugin\AiFunctionCall\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai_automators\Entity\AutomatorsTool;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a tools config for each entity type with specific interfaces.
 */
class AutomatorPluginDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new EntityActionDeriverBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config schema manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (empty($this->derivatives)) {
      $definitions = [];
      /** @var \Drupal\ai_automators\Entity\AutomatorsTool $tool */
      foreach ($this->entityTypeManager->getStorage('automators_tool')->loadMultiple() as $tool) {
        $definition = $base_plugin_definition;
        $definition['id'] = 'automator_plugin:' . $tool->id();
        $definition['type'] = 'automator_tool';
        $definition['name'] = $tool->label();
        $definition['group'] = 'automators_tools';
        $definition['function_name'] = 'automator_plugin__' . $tool->id();
        $definition['description'] = $tool->get('description');
        if ($context_definitions = $this->getContextDefinitions($tool)) {
          $definition['context_definitions'] = $context_definitions;
        }
        $definitions[$tool->id()] = $definition;
      }
      $this->derivatives = $definitions;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  /**
   * Get the context definitions for the tool.
   *
   * @param \Drupal\ai_automators\Entity\AutomatorsTool $tool
   *   The automator tool.
   *
   * @return array
   *   An array of context definitions.
   */
  protected function getContextDefinitions(AutomatorsTool $tool) {
    $context_definitions = [];
    foreach ($tool->get('field_connections') as $data) {
      $constraints = [];
      // We only need the inputs.
      if ($data['agent_process'] == 'input') {
        $context_definitions[$data['field_name']] = new ContextDefinition(
          $data['tool_field_type'],
          $data['field_name'],
          $data['required'],
          FALSE,
          $data['input_explanation'],
          $data['default_value'] ?? NULL,
          $constraints,
        );
      }
    }
    return $context_definitions;
  }

}
