<?php

namespace Drupal\jsonapi_extras\Plugin;

use Drupal\Core\Plugin\PluginBase;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Shaper\DataAdaptor\DataAdaptorTransformerTrait;
use Shaper\DataAdaptor\DataAdaptorValidatorTrait;
use Shaper\Validator\AcceptValidator;
use Shaper\Validator\JsonSchemaValidator;

/**
 * Common base class for resourceFieldEnhancer plugins.
 *
 * @see \Drupal\jsonapi_extras\Annotation\ResourceFieldEnhancer
 * @see \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager
 * @see \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerInterface
 * @see plugin_api
 *
 * @ingroup third_party
 */
abstract class ResourceFieldEnhancerBase extends PluginBase implements ResourceFieldEnhancerInterface {

  use DataAdaptorValidatorTrait;
  use DataAdaptorTransformerTrait;

  /**
   * Holds the plugin configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    // @todo This should have a dependency on the resource_config entity.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration
      ? $this->configuration
      : $this->setConfiguration([]);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $resource_field_info) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getInternalValidator() {
    return new AcceptValidator();
  }

  /**
   * {@inheritdoc}
   */
  public function getInputValidator() {
    // @todo Implement a getInputJsonSchema method.
    return new AcceptValidator();
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputValidator() {
    return new JsonSchemaValidator($this->getOutputJsonSchema(), new Validator(), Constraint::CHECK_MODE_TYPE_CAST);
  }

}
