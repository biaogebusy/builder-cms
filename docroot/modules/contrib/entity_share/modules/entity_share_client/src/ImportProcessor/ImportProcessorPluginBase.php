<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\ImportProcessor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_share_client\RuntimeImportContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class from which other processors may extend.
 *
 * Plugins extending this class need to define a plugin definition array through
 * annotation. The definition includes the following keys:
 * - id: The unique, system-wide identifier of the processor.
 * - label: The human-readable name of the processor, translated.
 * - description: A human-readable description for the processor, translated.
 * - stages: The default weights for all stages for which the processor should
 *   run. Available stages are defined by the STAGE_* constants in
 *   ImportProcessorInterface. This is, by default, used for supportsStage(),
 *   so if you don't provide a value here, your processor might not work as
 *   expected even though it implements the corresponding method.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * @ImportProcessor(
 *   id = "my_processor",
 *   label = @Translation("My Processor"),
 *   description = @Translation("Does … something."),
 *   stages = {
 *     "prepare_entity_data" = 0,
 *     "is_entity_importable" = 0,
 *     "prepare_importable_entity_data" = 0,
 *     "process_entity" = 0,
 *     "post_entity_save" = 0,
 *   },
 *   locked = false,
 * )
 * @endcode
 */
abstract class ImportProcessorPluginBase extends PluginBase implements ImportProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $plugin_definition = $this->getPluginDefinition();
    $label = $plugin_definition['label'];
    if ($label instanceof TranslatableMarkup) {
      $label = $label->render();
    }
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $plugin_definition = $this->getPluginDefinition();
    $description = '';
    if (isset($plugin_definition['description'])) {
      $description = $plugin_definition['description'];
      if ($description instanceof TranslatableMarkup) {
        $description = $description->render();
      }
    }

    return $description;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function supportsStage($stage) {
    $plugin_definition = $this->getPluginDefinition();
    return isset($plugin_definition['stages'][$stage]);
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight($stage) {
    if (isset($this->configuration['weights'][$stage])) {
      return $this->configuration['weights'][$stage];
    }
    $plugin_definition = $this->getPluginDefinition();
    if (isset($plugin_definition['stages'][$stage])) {
      return (int) $plugin_definition['stages'][$stage];
    }
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($stage, $weight) {
    $this->configuration['weights'][$stage] = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    return !empty($this->pluginDefinition['locked']);
  }

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the plugin form as built
   *   by static::buildConfigurationForm().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityImportable(RuntimeImportContext $runtime_import_context, array $entity_json_data) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data) {
  }

  /**
   * {@inheritdoc}
   */
  public function processEntity(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity, array $entity_json_data) {
  }

  /**
   * {@inheritdoc}
   */
  public function postEntitySave(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity) {
  }

}
