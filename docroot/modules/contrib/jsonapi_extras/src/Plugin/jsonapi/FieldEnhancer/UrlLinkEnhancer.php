<?php

namespace Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add URL aliases to links.
 *
 * @ResourceFieldEnhancer(
 *   id = "url_link",
 *   label = @Translation("URL for link (link field only)"),
 *   description = @Translation("Use Url for link fields.")
 * )
 */
class UrlLinkEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs UrlLinkEnhancer.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    LanguageManagerInterface $language_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
    $this->logger = $logger_factory->get('jsonapi_extras');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'absolute_url' => 0,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getSettingsForm(array $resource_field_info) {
    $settings = empty($resource_field_info['enhancer']['settings'])
      ? $this->getConfiguration()
      : $resource_field_info['enhancer']['settings'];
    $form = parent::getSettingsForm($resource_field_info);
    $form['absolute_url'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Get Absolute Urls'),
      '#default_value' => $settings['absolute_url'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    if (isset($data['uri'])) {
      try {
        $url = Url::fromUri($data['uri'], ['language' => $this->languageManager->getCurrentLanguage()]);

        // Use absolute urls if configured.
        $configuration = $this->getConfiguration();
        if ($configuration['absolute_url']) {
          $url->setAbsolute(TRUE);
        }

        $data['url'] = $url->toString();
      }
      catch (\Exception $e) {
        $this->logger->error('Failed to create a URL from uri @uri. Error: @error', [
          '@uri' => $data['uri'],
          '@error' => $e->getMessage(),
        ]);
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($value, Context $context) {
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'type' => 'object',
      'properties' => [
        'uri' => ['type' => 'string'],
        'title' => [
          'anyOf' => [
            ['type' => 'null'],
            ['type' => 'string'],
          ],
        ],
        'options' => [
          'anyOf' => [
            ['type' => 'array'],
            ['type' => 'object'],
          ],
        ],
        'url' => ['type' => 'string'],
      ],
    ];
  }

}
