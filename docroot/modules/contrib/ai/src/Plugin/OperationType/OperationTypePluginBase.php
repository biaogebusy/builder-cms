<?php

namespace Drupal\ai\Plugin\OperationType;

use Drupal\ai\OperationType\OperationTypeInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for operation type plugins.
 */
abstract class OperationTypePluginBase extends PluginBase implements OperationTypeInterface {

  use StringTranslationTrait;

  /**
   * The operation type interface class.
   *
   * @var string
   */
  protected string $interfaceClass;

  /**
   * {@inheritdoc}
   */
  public function getInterfaceClass(): string {
    return $this->interfaceClass;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getActualType(): string {
    return $this->pluginDefinition['actual_type'] ?? $this->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getFilter(): array {
    return $this->pluginDefinition['filter'] ?? [];
  }

}
