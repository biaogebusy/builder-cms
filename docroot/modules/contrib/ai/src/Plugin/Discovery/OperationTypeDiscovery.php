<?php

namespace Drupal\ai\Plugin\Discovery;

use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Plugin\Discovery\AttributeClassDiscovery;

/**
 * Discovers operation types from interfaces and their plugin classes.
 */
class OperationTypeDiscovery implements DiscoveryInterface {

  /**
   * The attribute discovery.
   *
   * @var \Drupal\Core\Plugin\Discovery\AttributeClassDiscovery
   */
  protected AttributeClassDiscovery $attributeDiscovery;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new OperationTypeDiscovery.
   *
   * @param \Drupal\Core\Plugin\Discovery\AttributeClassDiscovery $attribute_discovery
   *   The attribute discovery.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    AttributeClassDiscovery $attribute_discovery,
    $module_handler,
  ) {
    $this->attributeDiscovery = $attribute_discovery;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = [];
    $base_path = $this->moduleHandler->getModule('ai')->getPath() . '/src/OperationType';
    $directories = new \RecursiveDirectoryIterator($base_path);
    $iterator = new \RecursiveIteratorIterator($directories);
    $regex = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

    foreach ($regex as $file) {
      $interface = $this->getInterfaceFromFile($file[0]);
      if ($interface && $this->doesInterfaceExtend($interface, OperationTypeInterface::class)) {
        $reflection = new \ReflectionClass($interface);
        $attributes = $reflection->getAttributes(OperationType::class);
        foreach ($attributes as $attribute) {
          $instance = $attribute->newInstance();
          $plugin_id = $instance->id;

          // Create a plugin class name based on the interface name.
          $interface_parts = explode('\\', $interface);
          $interface_name = end($interface_parts);
          $plugin_class = 'Drupal\\ai\\Plugin\\OperationType\\' . $interface_name . 'Plugin';

          $definitions[$plugin_id] = [
            'id' => $plugin_id,
            'label' => $instance->label,
            'class' => $plugin_class,
            'interface_class' => $interface,
            'actual_type' => $instance->actual_type ?? $plugin_id,
            'filter' => $instance->filter ?? [],
            'description' => $instance->description ?? NULL,
          ];
        }
      }
    }

    // Allow modules to alter the operation type definitions.
    $this->moduleHandler->alter('ai_operationtype', $definitions);

    return $definitions;
  }

  /**
   * Extracts the fully qualified interface name from a file.
   *
   * @param string $file
   *   The file path.
   *
   * @return string|null
   *   The fully qualified interface name, or NULL if not found.
   */
  protected function getInterfaceFromFile($file) {
    $contents = file_get_contents($file);

    // Match namespace and interface declarations.
    if (preg_match('/namespace\s+([^;]+);/i', $contents, $matches)) {
      $namespace = $matches[1];
    }

    // Match on starts with interface and has extends in it.
    if (preg_match('/interface\s+([^ ]+)\s+extends\s+([^ ]+)/i', $contents, $matches) && isset($namespace)) {
      $interface = $matches[1];
      return $namespace . '\\' . $interface;
    }

    return NULL;
  }

  /**
   * Checks if an interface extends another interface.
   *
   * @param string $interface
   *   The interface to check.
   * @param string $parent
   *   The parent interface.
   *
   * @return bool
   *   TRUE if the interface extends the parent, FALSE otherwise.
   */
  protected function doesInterfaceExtend($interface, $parent) {
    try {
      $reflection = new \ReflectionClass($interface);
      return $reflection->isInterface() && $reflection->implementsInterface($parent);
    }
    catch (\ReflectionException) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    $definitions = $this->getDefinitions();
    return isset($definitions[$plugin_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    $definitions = $this->getDefinitions();
    if (isset($definitions[$plugin_id])) {
      return $definitions[$plugin_id];
    }
    if ($exception_on_invalid) {
      throw new \InvalidArgumentException(sprintf('The "%s" operation type does not exist.', $plugin_id));
    }
    return NULL;
  }

}
