<?php

declare(strict_types=1);

namespace Drupal\ai\Service\FunctionCalling;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutputInterface;

/**
 * Function call plugin manager.
 */
final class FunctionCallPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AiFunctionCall', $namespaces, $module_handler, FunctionCallInterface::class, FunctionCall::class);
    $this->alterInfo('ai_function_call_info');
    $this->setCacheBackend($cache_backend, 'ai_function_call_info_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = $this->getCachedDefinitions();
    if (!isset($definitions)) {
      $definitions = $this->findDefinitions();
      foreach ($definitions as $id => $definition) {
        if (!empty($definition['module_dependencies'])) {
          // Check if all modules are installed, otherwise remove this.
          foreach ($definition['module_dependencies'] as $module) {
            if (!$this->moduleHandler->moduleExists($module)) {
              unset($definitions[$id]);
              break;
            }
          }
        }
      }
      // Cache.
      $this->setCachedDefinitions($definitions);
    }
    return $definitions;
  }

  /**
   * Helper function to check if a function name exists.
   *
   * @param string $function_name
   *   The function name.
   *
   * @return bool
   *   If the function name exists.
   */
  public function functionExists(string $function_name): bool {
    // Load all definitions.
    $definitions = $this->getDefinitions();
    // Check if the function name exists.
    foreach ($definitions as $definition) {
      if ($definition['function_name'] === $function_name) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Convert a tools response to actual object.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutputInterface[] $tools
   *   The tools to convert into object.
   *
   * @return \Drupal\ai\Service\FunctionCalling\FunctionCallInterface[]
   *   The object filled out.
   */
  public function convertToolsResponseToObjects(array $tools): array {
    $objects = [];
    foreach ($tools as $tool) {
      $objects[] = $this->convertToolResponseToObject($tool);
    }
    return $objects;
  }

  /**
   * Convert a single tools response to actual object.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput $tool
   *   The tool to convert into object.
   *
   * @return \Drupal\ai\Service\FunctionCalling\FunctionCallInterface
   *   The object filled out.
   */
  public function convertToolResponseToObject(ToolsFunctionOutputInterface $tool): FunctionCallInterface {
    $function = $this->getFunctionCallFromFunctionName($tool->getName());
    $function->populateValues($tool);
    if ($tool->getToolId()) {
      $function->setToolsId($tool->getToolId());
    }
    return $function;
  }

  /**
   * Get function call from function name.
   *
   * @param string $function_name
   *   The function name.
   *
   * @return \Drupal\ai\Service\FunctionCalling\FunctionCallInterface
   *   The function call.
   */
  public function getFunctionCallFromFunctionName(string $function_name): FunctionCallInterface {
    foreach ($this->getDefinitions() as $definition) {
      if ($definition['function_name'] === $function_name) {
        return $this->createInstance($definition['id']);
      }
    }
    throw new \Exception('Function with name ' . $function_name . ' not found.');
  }

  /**
   * Get the function call from the class.
   *
   * @param string $class
   *   The class.
   *
   * @return \Drupal\ai\Service\FunctionCalling\FunctionCallInterface
   *   The function call.
   */
  public function getFunctionCallFromClass(string $class): FunctionCallInterface {
    foreach ($this->getDefinitions() as $definition) {
      if ($definition['class'] === $class) {
        return $this->createInstance($definition['id']);
      }
    }
    throw new \Exception('Function with class ' . $class . ' not found.');
  }

  /**
   * Get executable function call definitions.
   *
   * @return array
   *   An array of function call definitions.
   */
  public function getExecutableDefinitions(): array {
    $definitions = $this->getDefinitions();
    $executable_definitions = [];
    foreach ($definitions as $definition) {
      $implements = class_implements($definition['class']);
      if (in_array(ExecutableFunctionCallInterface::class, $implements)) {
        $executable_definitions[$definition['id']] = $definition;
      }
    }
    return $executable_definitions;
  }

  /**
   * Get structured executable function call definitions.
   *
   * @return array
   *   An array of structured executable function call definitions.
   */
  public function getStructuredExecutableDefinitions(): array {
    $definitions = $this->getDefinitions();
    $structured_executable_definitions = [];
    foreach ($definitions as $definition) {
      $implements = class_implements($definition['class']);
      if (in_array(StructuredExecutableFunctionCallInterface::class, $implements)) {
        $structured_executable_definitions[$definition['id']] = $definition;
      }
    }
    return $structured_executable_definitions;
  }

}
