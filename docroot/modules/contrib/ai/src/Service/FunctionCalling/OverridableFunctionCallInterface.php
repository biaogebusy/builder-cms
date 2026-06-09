<?php

namespace Drupal\ai\Service\FunctionCalling;

use Drupal\Core\Plugin\Context\ContextDefinitionInterface;

/**
 * Interface for function call plugins that support per-instance overrides.
 *
 * Context definitions declared in the #[FunctionCall] attribute are shared by
 * every instance of the plugin. Implementations of this interface allow a
 * single plugin instance to override or add context definitions without
 * affecting other instances or the cached plugin definition.
 *
 * Overrides set via setContextDefinitionOverride() take precedence over the
 * definitions declared in the plugin attribute when resolved through
 * getContextDefinition() and getContextDefinitions().
 *
 * @see \Drupal\ai\Base\FunctionCallBase::setContextDefinitionOverride()
 * @see \Drupal\ai\Base\FunctionCallBase::getContextDefinition()
 * @see \Drupal\ai\Base\FunctionCallBase::getContextDefinitions()
 */
interface OverridableFunctionCallInterface extends FunctionCallInterface {

  /**
   * Overrides a context definition for this plugin instance.
   *
   * The override applies only to the instance it is set on. It takes
   * precedence over the definition declared in the plugin attribute when
   * resolved through getContextDefinition() and getContextDefinitions(), and
   * may also introduce an entirely new context definition that was not
   * declared on the plugin.
   *
   * @param string $name
   *   The context definition name.
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface $definition
   *   The context definition to use for this instance.
   */
  public function setContextDefinitionOverride(string $name, ContextDefinitionInterface $definition): void;

}
