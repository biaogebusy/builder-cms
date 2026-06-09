<?php

namespace Drupal\ai\Plugin\AiFunctionCall\Derivative;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Action\ActionInterface;
use Drupal\Core\Action\ActionManager;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\Attribute\DataType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base action for each entity type with specific interfaces.
 */
class ActionPluginDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new EntityActionDeriverBase object.
   *
   * @param \Drupal\Core\Action\ActionManager $actionManager
   *   The action manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config schema manager.
   */
  public function __construct(
    protected ActionManager $actionManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new self(
      $container->get('plugin.manager.action'),
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
      $supported_action_types = ['entity', 'system'];

      foreach ($this->actionManager->getDefinitions() as $id => $action_definition) {
        // @todo add conditions for all types of actions we want to support.
        if (in_array($action_definition['type'], $supported_action_types, TRUE) || $this->entityTypeManager->getDefinition($action_definition['type'], FALSE)) {
          if (!empty($action_definition['confirm_form_route_name'])) {
            continue;
          }
          $action_plugin = $this->actionManager->createInstance($id);
          if (!$this->isValidConfigSchema($action_plugin)) {
            continue;
          }
          $definition = $base_plugin_definition;
          $definition['id'] = 'action_plugin:' . $id;
          $definition['type'] = $action_definition['type'];
          $definition['name'] = $action_definition['label'];
          $definition['group'] = 'drupal_actions';
          $definition['function_name'] = 'action_plugin__' . str_replace(':', '__', $id);
          $definition['description'] = $action_definition['description'] ?? $action_definition['label'];
          if ($context_definitions = $this->getContextDefinitions($action_plugin)) {
            $definition['context_definitions'] = $context_definitions;
          }
          $definitions[$id] = $definition;
        }
      }
      $this->derivatives = $definitions;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  /**
   * Check if the action plugin has a valid configuration schema.
   *
   * If action has configuration and no schema, then we are unable to describe
   * the configuration to the AI service.
   *
   * @param \Drupal\Core\Action\ActionInterface $action_plugin
   *   The action plugin.
   *
   * @return bool
   *   FALSE if the configuration schema is invalid, TRUE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function isValidConfigSchema(ActionInterface $action_plugin) {
    if ($action_plugin instanceof ConfigurableInterface) {
      // Only validate if configuration exists.
      if ($configuration = $action_plugin->getConfiguration()) {
        $config_schema_definition = NULL;
        if ($this->typedConfigManager->hasConfigSchema('action.configuration.' . $action_plugin->getPluginId())) {
          $config_schema_definition = $this->typedConfigManager->getDefinition('action.configuration.' . $action_plugin->getPluginId());
        }
        elseif ($entity_type = $this->entityTypeManager->getDefinition($action_plugin->getPluginDefinition()['type'], FALSE)) {
          if ($this->typedConfigManager->hasConfigSchema('action.configuration.entity:' . $entity_type->id())) {
            $config_schema_definition = $this->typedConfigManager->getDefinition('action.configuration.entity:' . $entity_type->id());
          }
        }
        // If configuration exists and no schema is defined, then return FALSE.
        if (empty($config_schema_definition)) {
          return FALSE;
        }
        foreach ($configuration as $key => $value) {
          if (!isset($config_schema_definition['mapping'][$key])) {
            return FALSE;
          }
        }
      }
    }
    return TRUE;
  }

  /**
   * Get the data types for the action plugin.
   *
   * @param \Drupal\Core\Action\ActionInterface $action_plugin
   *   The action plugin.
   *
   * @return array
   *   An array of data types.
   */
  protected function getContextDefinitions(ActionInterface $action_plugin) {
    $context_definitions = [];

    if ($action_plugin->getPluginDefinition()['type'] === 'entity') {
      $context_definitions['entity'] = new ContextDefinition('entity', $this->t('Entity'), TRUE, FALSE, $this->t("The contextual entity used to perform the action."));
    }
    elseif ($entity_type = $this->entityTypeManager->getDefinition($action_plugin->getPluginDefinition()['type'], FALSE)) {
      $entity_context = EntityContextDefinition::fromEntityType($entity_type);

      // Enhance description with format example for AI agents.
      $original_description = $entity_context->getDescription();
      $enhanced_description = ($original_description ?: 'Entity') . ' ' . $this->t('(e.g. @example)', [
        '@example' => $entity_type->id() . ':123',
      ]);
      $entity_context->setDescription($enhanced_description);

      $context_definitions['entity:' . $entity_type->id()] = $entity_context;
    }

    if ($action_plugin instanceof ConfigurableInterface) {
      // Only validate if configuration exists.
      $config_schema_definition = NULL;
      if ($action_plugin->getConfiguration()) {
        if ($this->typedConfigManager->hasConfigSchema('action.configuration.' . $action_plugin->getPluginId())) {
          $config_schema_definition = $this->typedConfigManager->getDefinition('action.configuration.' . $action_plugin->getPluginId());
        }
        elseif ($entity_type = $this->entityTypeManager->getDefinition($action_plugin->getPluginDefinition()['type'], FALSE)) {
          if ($this->typedConfigManager->hasConfigSchema('action.configuration.entity:' . $entity_type->id())) {
            $config_schema_definition = $this->typedConfigManager->getDefinition('action.configuration.entity:' . $entity_type->id());
          }
        }
        // @todo Handle missing schema.
        foreach ($config_schema_definition['mapping'] as $key => $info) {
          $constraints = $info['constraints'] ?? [];

          // Create context-specific descriptions.
          $description = $info['description'] ?? $info['label'];
          if ($description === $info['label']) {
            // Provide specific guidance for common field types.
            switch ($key) {
              case 'url':
                $description = $this->t('(e.g. /node/123 or https://example.com)');
                break;

              case 'message':
                $description = $this->t('(e.g. The action was successful!)');
                break;

              default:
                $description = $this->t('(e.g. enter the @label)', ['@label' => strtolower($info['label'])]);
                break;
            }
          }

          if ($info['type'] === 'sequence' && isset($info['sequence']['type'])) {
            $type = $this->resolveRootDataType($info['sequence']['type']);
          }
          // @todo Handle mapping type.
          else {
            $type = $this->resolveRootDataType($info['type']);
          }
          if (!empty($type)) {
            $context_definitions[$key] = new ContextDefinition($type, $info['label'], $info['required'] ?? FALSE, FALSE, $description, $info['default_value'] ?? NULL, $constraints);
          }
        }
      }
    }

    return $context_definitions;
  }

  /**
   * Resolve the root data type for the given type.
   *
   * @param string $type
   *   The type to resolve.
   *
   * @return string
   *   The resolved root data type. An empty string on failure.
   */
  protected function resolveRootDataType(string $type): string {
    $definition = $this->typedConfigManager->getDefinition($type);
    $reflection_class = new \ReflectionClass($definition['class']);
    $attributes = $reflection_class->getAttributes(DataType::class);

    // Make sure there are available attributes.
    if (!empty($attributes)) {
      return $attributes[0]->newInstance()->id;
    }

    // Since there aren't any attributes, return an empty string.
    return '';
  }

}
