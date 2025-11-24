<?php

namespace Drupal\media_entity_instagram\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media\Entity\MediaType;
use Drupal\media\IFrameMarkup;
use Drupal\media\IFrameUrlHelper;
use Drupal\media\OEmbed\Resource;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\UrlResolverInterface;
use Drupal\media_entity_instagram\Plugin\media\Source\Instagram;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'instagram_embed' formatter.
 */
#[FieldFormatter(
  id: 'instagram_embed',
  label: new TranslatableMarkup('Instagram embed'),
  field_types: [
    'link',
    'string',
    'string_long',
  ],
)]
class InstagramEmbedFormatter extends FormatterBase {

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The media settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructs an OEmbedFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin ID for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\media\OEmbed\ResourceFetcherInterface $resourceFetcher
   *   The oEmbed resource fetcher service.
   * @param \Drupal\media\OEmbed\UrlResolverInterface $urlResolver
   *   The oEmbed URL resolver service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\media\IFrameUrlHelper $iframeUrlHelper
   *   The iFrame URL helper service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, MessengerInterface $messenger, protected readonly ResourceFetcherInterface $resourceFetcher, protected readonly UrlResolverInterface $urlResolver, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, protected readonly IFrameUrlHelper $iframeUrlHelper) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->messenger = $messenger;
    $this->logger = $logger_factory->get('media');
    $this->config = $config_factory->get('media.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('messenger'),
      $container->get('media.oembed.resource_fetcher'),
      $container->get('media_entity_instagram.oembed.url_resolver'),
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('media.oembed.iframe_url_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'max_width' => 0,
      'hidecaption' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    $max_width = $this->getSetting('max_width');

    foreach ($items as $delta => $item) {
      $main_property = $item->getFieldDefinition()->getFieldStorageDefinition()->getMainPropertyName();
      $value = $item->{$main_property};

      if (empty($value)) {
        continue;
      }

      try {
        $resource_url = $this->urlResolver->getResourceUrl($value, $max_width, NULL, $this->getSettings());
        $resource = $this->resourceFetcher->fetchResource($resource_url);
      }
      catch (ResourceException $exception) {
        $this->logger->error("Could not retrieve the remote URL (@url).", ['@url' => $value]);
        continue;
      }

      switch ($resource->getType()) {
        case Resource::TYPE_RICH:
          $element[$delta] = [
            '#theme' => 'media_oembed_iframe',
            '#resource' => $resource,
            '#media' => IFrameMarkup::create($resource->getHtml()),
            '#attached' => [
              'library' => [
                'media_entity_instagram/integration',
              ],
            ],
          ];

          CacheableMetadata::createFromObject($resource)
            ->addCacheTags($this->config->getCacheTags())
            ->applyTo($element[$delta]);
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return parent::settingsForm($form, $form_state) + [
      'max_width' => [
        '#type' => 'number',
        '#title' => $this->t('Maximum width'),
        '#default_value' => $this->getSetting('max_width'),
        '#size' => 5,
        '#field_suffix' => $this->t('pixels'),
        '#max' => 658,
        '#min' => 0,
      ],
      'hidecaption' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Hide caption'),
        '#default_value' => $this->getSetting('hidecaption'),
        '#description' => $this->t('Hide caption of Instagram posts.'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Maximum width: %max_width pixels', [
      '%max_width' => $this->getSetting('max_width'),
    ]);
    $summary[] = $this->t('Caption: @hidecaption', [
      '@hidecaption' => $this->getSetting('hidecaption') ? $this->t('Hidden') : $this->t('Visible'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    if (parent::isApplicable($field_definition)) {
      $media_type = $field_definition->getTargetBundle();

      if ($media_type) {
        $media_type = MediaType::load($media_type);
        return $media_type && $media_type->getSource() instanceof Instagram;
      }
    }
    return FALSE;
  }

}
