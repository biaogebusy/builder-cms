<?php

declare(strict_types = 1);

namespace Drupal\entity_share\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Drupal\pathauto\PathautoState;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prepare path value to be able to handle pathauto metadata.
 *
 * @ResourceFieldEnhancer(
 *   id = "entity_share_pathauto",
 *   label = @Translation("Pathauto (Path field only) (Entity Share)"),
 *   description = @Translation("Prepare path value to be able to handle pathauto metadata."),
 *   dependencies = {"pathauto"}
 * )
 */
class EntitySharePathautoEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * Configuration value when exposing current state.
   */
  const EXPOSE_CURRENT_PATHAUTO = 'expose_current_pathauto';

  /**
   * Configuration value when the remote will use its pathauto.
   */
  const FORCE_ENABLE_PATHAUTO = 'force_enable_pathauto';

  /**
   * Configuration value when the remote will not use its pathauto.
   */
  const FORCE_DISABLE_PATHAUTO = 'force_disable_pathauto';

  /**
   * The key value service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValue;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    KeyValueFactoryInterface $key_value
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->keyValue = $key_value;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('keyvalue')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    $configuration = $this->getConfiguration();

    switch ($configuration['behavior']) {
      case self::EXPOSE_CURRENT_PATHAUTO:
        /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
        $field_item = $context['field_item_object'];
        $entity = $field_item->getEntity();

        $entity_type_id = $entity->getEntityTypeId();
        $key = PathautoState::getPathautoStateKey($entity->id());
        $state = $this->keyValue->get("pathauto_state.$entity_type_id")
          ->get($key);

        if (!is_null($state)) {
          $data['pathauto'] = $state;
        }
        else {
          $data['pathauto'] = 0;
        }
        break;

      case self::FORCE_ENABLE_PATHAUTO:
        $data['pathauto'] = 1;
        break;

      case self::FORCE_DISABLE_PATHAUTO:
        $data['pathauto'] = 0;
        break;
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($value, Context $context) {
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'type' => 'object',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'behavior' => self::EXPOSE_CURRENT_PATHAUTO,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $resource_field_info) {
    $settings = empty($resource_field_info['enhancer']['settings'])
      ? $this->getConfiguration()
      : $resource_field_info['enhancer']['settings'];

    return [
      'behavior' => [
        '#type' => 'select',
        '#title' => $this->t('Expose pathauto value'),
        '#description' => $this->t('If pathauto state is 0, the client website will use the path on the server website (generated with Pathauto or not). If pathauto state is 1, the client website will generate a new alias according to its own configuration if Pathauto patterns are used on the client website.'),
        '#required' => TRUE,
        '#options' => [
          self::EXPOSE_CURRENT_PATHAUTO => $this->t('Expose current pathauto state'),
          self::FORCE_ENABLE_PATHAUTO => $this->t('Force to expose a pathauto state enabled'),
          self::FORCE_DISABLE_PATHAUTO => $this->t('Force to expose a pathauto state disabled'),
        ],
        '#default_value' => $settings['behavior'],
      ],
    ];
  }

}
