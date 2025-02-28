<?php

namespace Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Perform additional manipulations to JSON fields.
 *
 * @ResourceFieldEnhancer(
 *   id = "json",
 *   label = @Translation("JSON Field"),
 *   description = @Translation("Render JSON Field has real json")
 * )
 */
class JSONFieldEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * The serialization json.
   *
   * @var Drupal\Component\serialization\Json
   */
  protected $encoder;

  /**
   * Constructs a new JSONFieldEnhancer.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Serialization\Json $encoder
   *   The serialization json.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, Json $encoder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->encoder = $encoder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('serialization.json'));
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
  public function doUndoTransform($data, Context $context) {
    return $this->encoder->decode($data);
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    return $this->encoder->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'oneOf' => [
        ['type' => 'object'],
        ['type' => 'array'],
        ['type' => 'null'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $resource_field_info) {
    return [];
  }

}
