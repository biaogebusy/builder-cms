<?php

namespace Drupal\state_machine\Plugin\WorkflowGroup;

use Drupal\Core\Plugin\PluginBase;

/**
 * Defines the class for workflow groups.
 */
class WorkflowGroup extends PluginBase implements WorkflowGroupInterface {

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->pluginDefinition['entity_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowClass() {
    return $this->pluginDefinition['workflow_class'];
  }

}
