<?php

declare(strict_types = 1);

namespace Drupal\entity_share\Plugin\jsonapi\FieldEnhancer;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Token;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Drupal\metatag\MetatagManagerInterface;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prepare metatag value to be able to shared.
 *
 * @ResourceFieldEnhancer(
 *   id = "entity_share_metatag",
 *   label = @Translation("Metatag (Metatag field only) (Entity Share)"),
 *   description = @Translation("Prepare metatag value to be able to be shared."),
 *   dependencies = {"metatag"}
 * )
 */
class EntityShareMetatagEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * The metatag manager.
   *
   * @var \Drupal\metatag\MetatagManagerInterface
   */
  protected $metatagManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    MetatagManagerInterface $metatag_manager,
    Token $token
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->metatagManager = $metatag_manager;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('metatag.manager'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    // Export.
    $configuration = $this->getConfiguration();

    if ($configuration['expose_default_tags']) {
      // Inspired from MetatagManager::generateRawElements() and from
      // https://www.drupal.org/project/metatag/issues/2945817#comment-13079626.
      /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
      $field_item = $context['field_item_object'];
      $entity = $field_item->getEntity();

      $metatags_for_entity = $this->metatagManager->tagsFromEntityWithDefaults($entity);

      if ($configuration['replace_tokens']) {
        $token_replacements = [$entity->getEntityTypeId() => $entity];
        $replacements_options = [];
        if ($configuration['clear_tokens']) {
          $replacements_options['clear'] = TRUE;
        }
        $data = [];
        foreach ($metatags_for_entity as $metatag_key => $metatag_for_entity) {
          $data[$metatag_key] = PlainTextOutput::renderFromHtml(htmlspecialchars_decode($this->token->replace($metatag_for_entity, $token_replacements, $replacements_options)));

          if (empty($data[$metatag_key])) {
            unset($data[$metatag_key]);
          }
        }
      }
      else {
        $data = $metatags_for_entity;
      }
    }

    return [
      'value' => $data,
    ];
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
      'expose_default_tags' => TRUE,
      'replace_tokens' => TRUE,
      'clear_tokens' => TRUE,
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
      'expose_default_tags' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Expose default tags'),
        '#description' => $this->t('Expose tags that have a default value, usually with tokens, and are not overridden in the entity.'),
        '#default_value' => $settings['expose_default_tags'],
      ],
      'replace_tokens' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Replace tokens'),
        '#description' => $this->t('Replace tokens by its value.'),
        '#default_value' => $settings['replace_tokens'],
        '#states' => [
          'visible' => [
            ':input[name="resourceFields[' . $resource_field_info['fieldName'] . '][enhancer][settings][expose_default_tags]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],
      'clear_tokens' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Clear tokens'),
        '#description' => $this->t('Remove tokens from the final text if no replacement value can be generated.'),
        '#default_value' => $settings['clear_tokens'],
        '#states' => [
          'visible' => [
            ':input[name="resourceFields[' . $resource_field_info['fieldName'] . '][enhancer][settings][expose_default_tags]"]' => [
              'checked' => TRUE,
            ],
            ':input[name="resourceFields[' . $resource_field_info['fieldName'] . '][enhancer][settings][replace_tokens]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ],
    ];
  }

}
