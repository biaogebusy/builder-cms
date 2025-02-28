<?php

namespace Drupal\blazy\Plugin\Field\FieldFormatter;

use Drupal\blazy\Dejavu\BlazyVideoBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyVideoFormatter is deprecated in blazy:8.x-2.0 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);

/**
 * Plugin implementation of the 'Blazy Video' to get VEF videos.
 *
 * This file is no longer used nor needed, and will be removed at 3.x.
 * VEF will continue working via BlazyOEmbed instead.
 *
 * BVEF can take over this file to be compat with Blazy 3.x rather than keeping
 * 1.x debris. Also to adopt core OEmbed security features at ease.
 *
 * How to:
 * - Put this file in the src\Plugin\Field\FieldFormatter namespace.
 * - Along with \Drupal\blazy\Dejavu\BlazyVideoBase with updated namespace.
 * - Copy `field.formatter.settings.blazy_vef_default:` from blazy.schema.yml
 *   into config/schema directory.
 *
 * @todo remove prior to full release. This means Slick Video which depends
 * on VEF is deprecated for main Slick at Blazy 8.2.x with core Media only.
 * @nottodo make is useful for local video instead? No!
 */
class BlazyVideoFormatter extends BlazyVideoBase {

  /**
   * {@inheritdoc}
   */
  protected static $fieldType = 'entity';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return static::injectServices($instance, $container, static::$fieldType);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Early opt-out if the field is empty.
    if ($items->isEmpty()) {
      return [];
    }

    return $this->commonViewElements($items, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->getType() === 'video_embed_field';
  }

  /**
   * Build the blazy elements.
   */
  protected function buildElements(array &$build, $items, $langcode) {
    $settings = $build['#settings'];
    $limit    = $this->getViewLimit($settings);
    $entity   = $items->getEntity();

    if (!($vef = $this->vefProviderManager())) {
      return;
    }

    foreach ($items as $delta => $item) {
      // If a Views display, bail out if more than Views delta_limit.
      // @todo figure out why Views delta_limit doesn't stop us here.
      if ($limit > 0 && $delta > $limit - 1) {
        break;
      }

      $input = $item->value;

      if (empty($input)
        || !($provider = $vef->loadProviderFromInput($input))) {
        continue;
      }

      // Ensures thumbnail is available.
      $provider->downloadThumbnail();

      // Addresses two render types: video_embed_iframe and html_tag.
      $uri       = $provider->getLocalThumbnailUri();
      $render    = $provider->renderEmbedCode(640, 360, '0');
      $old_url   = $render['#attributes']['src'] ?? $input;
      $embed_url = $render['#url'] ?? $old_url;
      $fragment  = $render['#fragment'] ?? '';
      $fragment  = $fragment ? '#' . $fragment : '';
      $query     = $render['#query'] ?? [];

      // Prevents complication with multiple videos by now.
      unset($query['autoplay'], $query['auto_play']);

      // Pass $embed_url to Blazy to be respected if `Use oEmbed` option is
      // disabled at Blazy UI. Relevant for Instagram or Facebook, etc. since
      // using oEmbed may require App ID and secret creds even for simple
      // oEmbed read, irrelevant for direct embed ala VEF.
      $embed_url = Url::fromUri($embed_url, ['query' => $query])->toString();

      // Update the settings, hard-coded, terracota.
      $sets = $settings;
      $info = [
        'delta' => $delta,
        'image.uri' => $uri,
        'is' => [
          'multimedia' => TRUE,
          'vef' => TRUE,
        ],
        'media' => [
          'bundle' => 'remote_video',
          'embed_url' => $embed_url . $fragment,
          'input_url' => $input,
          'provider' => $provider->getPluginId(),
          'source' => 'video_embed_field',
          'type' => 'video',
        ],
      ];

      /*
      // Too risky, but if you got lucky.
      // if ($media = $this->blazyManager->loadByProperty(
      // 'field_media_oembed_video.value', $input, 'media')) {
      // $entity = $media;
      // }
       */
      $data = [
        '#delta'    => $delta,
        '#entity'   => $entity,
        '#settings' => $this->formatter->toSettings($sets, $info),
        '#item'     => NULL,
      ];

      // Since 2.17, VEF embed is respected via Blazy UI option `Use oEmbed`,
      // or set by option via $blazies->set('use.oembed', FALSE).
      $this->blazyOembed->build($data);

      // Image with responsive image, lazyLoad, and lightbox supports.
      $build['items'][$delta] = $this->formatter->getBlazy($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    return [
      'fieldable_form' => TRUE,
      'multimedia'     => TRUE,
    ] + parent::getPluginScopes();
  }

}
