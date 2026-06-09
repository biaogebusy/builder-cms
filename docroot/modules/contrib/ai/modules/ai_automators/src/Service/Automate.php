<?php

namespace Drupal\ai_automators\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai_automators\Exceptions\AiAutomatorTypeNotFoundException;
use Drupal\ai_automators\Exceptions\AiAutomatorTypeNotRunnable;

/**
 * Automates anything using a disposable automator.
 */
class Automate {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * Excluded known fields.
   *
   * @var array
   */
  protected $excludedRequiresFields = [
    'bundle',
    'ai_automator_status',
  ];

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $fieldManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fieldManager = $fieldManager;
  }

  /**
   * Get all automator types.
   *
   * @return array
   *   The automator types with key/label.
   */
  public function getWorkflows() {
    $types = $this->entityTypeManager->getStorage('automator_chain_type')->loadMultiple();
    $output_types = [];
    foreach ($types as $type) {
      $output_types[$type->id()] = $type->label();
    }
    return $output_types;
  }

  /**
   * Get the required fields for input.
   *
   * @param string $type
   *   The type of the automator chain.
   *
   * @return array
   *   The fields that are required for input.
   */
  public function getRequiredFields(string $type) {
    $fields = $this->fieldManager->getFieldDefinitions('automator_chain', $type);

    $output_fields = [];
    foreach ($fields as $field) {
      if ($field->isRequired() && !in_array($field->getName(), $this->excludedRequiresFields)) {
        $output_fields[$field->getName()] = $field->getLabel();
      }
    }
    return $output_fields;
  }

  /**
   * Get the automated fields for a bundle.
   *
   * @param string $type
   *   The type of the automator chain.
   * @param array $field_types
   *   The types of the field to filter on (optional).
   *
   * @return array
   *   The fields that has automators on them.
   */
  public function getAutomatedFields(string $type, ?array $field_types = []) {
    $field_names = $this->fieldManager->getFieldDefinitions('automator_chain', $type);
    $fields = $this->entityTypeManager->getStorage('ai_automator')->loadByProperties([
      'entity_type' => 'automator_chain',
      'bundle' => $type,
    ]);
    $output_fields = [];
    /** @var \Drupal\field\Entity\FieldConfig */
    foreach ($fields as $field) {
      if (empty($field_types) || in_array($field_names[$field->get('field_name')]->getType(), $field_types)) {
        $output_fields[$field->get('field_name')] = $field_names[$field->get('field_name')]->getLabel();
      }
    }
    return $output_fields;
  }

  /**
   * Run the automator chain.
   *
   * @param string $type
   *   The type of the automator chain.
   * @param mixed $inputs
   *   The inputs to the automator chain.
   *
   * @return array
   *   The output of the automator chain.
   */
  public function run(string $type, $inputs = []) {
    // Check so the type exists.
    try {
      /** @var \Drupal\ai_automators\Entity\AiAutomatorChainType */
      $this->entityTypeManager->getStorage('automator_chain_type')->load($type);
    }
    catch (\Exception $e) {
      throw new AiAutomatorTypeNotFoundException('Automator chain type does not exist.');
    }

    // Check so there is output fields.
    $output_fields = $this->getAutomatedFields($type);
    // Load field types.
    $this->fieldManager->getFieldDefinitions('automator_chain', $type);

    /** @var \Drupal\ai_automators\Entity\AutomatorChain */
    $automator = $this->entityTypeManager->getStorage('automator_chain')->create([
      'bundle' => $type,
    ]);
    // Set the inputs.
    foreach ($inputs as $field => $input) {
      $automator->set($field, $input);
    }
    // Try saving.
    try {
      $automator->save();
    }
    catch (\Exception $e) {
      throw new AiAutomatorTypeNotRunnable('Automator chain could not be saved:' . $e->getMessage());
    }

    // Return the values that has automators on them.
    $output = [];
    foreach ($output_fields as $field_id => $field) {
      // Make sure to get the main value.
      $output[$field_id] = $automator->get($field_id)->getValue();
    }
    // Garbage collect.
    $automator->delete();
    return $output;
  }

}
