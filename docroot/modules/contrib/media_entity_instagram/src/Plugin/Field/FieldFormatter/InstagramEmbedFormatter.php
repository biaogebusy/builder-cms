<?php

namespace Drupal\media_entity_instagram\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\MediaType;
use Drupal\media\IFrameMarkup;
use Drupal\media\OEmbed\Resource;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\Plugin\Field\FieldFormatter\OEmbedFormatter;
use Drupal\media_entity_instagram\Plugin\media\Source\Instagram;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'instagram_embed' formatter.
 *
 * @FieldFormatter(
 *   id = "instagram_embed",
 *   label = @Translation("Instagram embed"),
 *   field_types = {
 *     "link", "string", "string_long"
 *   }
 * )
 */
class InstagramEmbedFormatter extends OEmbedFormatter {

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
  public static function defaultSettings() {
    $settings = parent::defaultSettings();
    $settings['hidecaption'] = FALSE;
    unset($settings['max_height']);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
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
        case Resource::TYPE_LINK:
        case Resource::TYPE_PHOTO:
          return parent::viewElements($items, $langcode);

        default:
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
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['hidecaption'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide caption'),
      '#default_value' => $this->getSetting('hidecaption'),
      '#description' => $this->t('Hide caption of Instagram posts.'),
    ];

    unset($elements['max_height']);
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Caption: @hidecaption', [
      '@hidecaption' => $this->getSetting('hidecaption') ? $this->t('Hidden') : $this->t('Visible'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
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
