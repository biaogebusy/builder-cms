<?php

namespace Drupal\blazy\Field;

use Drupal\blazy\BlazyDefault;
use Drupal\blazy\internals\Internals;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Media entity reference formatters with field details.
 *
 * @see \Drupal\blazy\Field\BlazyEntityReferenceBase
 */
abstract class BlazyEntityMediaBase extends BlazyEntityVanillaBase {

  use BlazyDependenciesTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->svgManager = $container->get('blazy.svg');
    return static::injectServices($instance, $container, static::$fieldType);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return BlazyDefault::mediaSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    if (isset($element['media_switch'])) {
      $element['media_switch']['#options']['rendered'] = $this->t('Image rendered by its formatter');
      $element['media_switch']['#description'] .= ' ' . $this->t('<b>Image rendered</b> requires <b>Image</b> option filled out and is useful if the formatter offers awesomeness that Blazy does not have but still wants Blazy for a Grid, etc. Be sure the enabled fields here are not hidden/ disabled at its view mode.');
    }

    if (isset($element['caption'])) {
      $element['caption']['#description'] = $this->t('Check fields to be treated as captions, even if not caption texts.');
    }

    if (isset($element['image']['#description'])) {
      $element['image']['#description'] .= ' ' . $this->t('The formatter/renderer is managed by <strong>@plugin_id</strong> formatter. Meaning original formatter ignored.', ['@plugin_id' => $this->getPluginId()]);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function withElementDetail(array $build): array {
    [
      '#entity'   => $entity,
      '#langcode' => $langcode,
      '#settings' => $settings,
    ] = $build;

    $blazies   = $settings['blazies'];
    $view_mode = $settings['view_mode'] ?? 'full';
    $is_blazy  = static::$namespace == 'blazy';
    $switch    = $settings['media_switch'] ?? NULL;
    $_image    = $settings['image'] ?? NULL;

    // Do not pass $build directly, even if easier, too early render errors,
    // and duplicated elements due to renderable array.
    $data = $this->formatter->withHashtag($build);
    $data['#settings'] = $this->manager->toSettings($settings);
    $data['#item'] = NULL;

    // Build media item including custom highres video thumbnail.
    $this->blazyOembed->build($data);

    // Captions if so configured, including Blazy formatters.
    $captions = $this->getCaptions($data);

    // @todo remove BC at blazy:3.x.
    $this->getCaption($data, $entity, $langcode);
    if (isset($data[static::$captionId])) {
      @trigger_error('getCaption is deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Field\BlazyEntityMediaBase::getCaptions() instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);
      $captions = array_merge($captions, $data[static::$captionId]);
      unset($data[static::$captionId]);
    }

    // If `Image rendered` is picked, render image as is. Might not be Blazy's
    // formatter, yet has awesomeness that Blazy doesn't, but still wants to be
    // embedded in Blazy ecosytem mostly for Grid, Slider, Mason, GridStack etc.
    if ($is_blazy && $_image && $switch == 'rendered') {
      if ($output = $this->viewField($entity, $_image, $view_mode)) {
        // Disable all lazy stuffs since we got a brick here.
        Internals::contently($settings);

        $data['content'][] = $output;
      }
    }

    // Provides the relevant elements based on the configuration.
    $element = $this->toElement($blazies, $data, $captions);

    // Provides extra elements.
    $this->withElementExtra($element);

    return $element;
  }

  /**
   * Returns the captions, if any.
   */
  protected function getCaptions(array $element): array {
    [
      '#entity'   => $entity,
      '#item'     => $item,
      '#langcode' => $langcode,
      '#settings' => $settings,
    ] = $element;

    $blazies   = $settings['blazies'];
    $view_mode = $settings['view_mode'] ?? 'full';
    $captions  = $items = $weights = [];
    $fields    = $settings['caption'] ?? [];
    $fields    = array_filter($fields);
    $_link     = $settings['link'] ?? NULL;
    $_title    = $settings['title'] ?? NULL;
    $_switch   = $settings['media_switch'] ?? NULL;
    $output    = [];

    // Title can be plain text, or link field.
    if ($_title) {
      $output = [];
      // If title is available as a field.
      if (isset($entity->{$_title})) {
        $output = BlazyField::getTextOrLink($entity, $_title, $view_mode, $langcode);
      }
      // Else fallback to image title property.
      elseif ($item && $_title == 'title') {
        // Respects both fake and real image item.
        if ($caption = trim($item->title ?? '')) {
          $caption = Xss::filter($caption, BlazyDefault::TAGS);
          $output = ['#markup' => $caption];
        }
      }

      if ($output) {
        $captions['title'] = $output;
      }
    }

    // The caption fields common to all entity formatters, if so configured.
    if ($fields) {
      foreach ($fields as $name => $field_caption) {
        /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
        if ($item) {
          // Provides basic captions based on image attributes (Alt, Title).
          foreach (['title', 'alt'] as $key => $attribute) {
            $value = $item->{$attribute} ?? '';
            if ($name == $attribute && $caption = trim($value)) {
              $markup = Xss::filter($caption, BlazyDefault::TAGS);
              if ($name == 'alt') {
                $markup = '<p>' . $markup . '</p>';
              }
              $items[$name] = ['#markup' => $markup];
              $weights[] = $key;
            }
          }
        }

        // Provides fieldable captions.
        if ($markup = $this->viewField($entity, $field_caption, $view_mode)) {
          if (isset($markup['#weight'])) {
            $weights[] = $markup['#weight'];
          }

          $items[$name] = $markup;
        }
      }
    }

    if ($items) {
      if ($weights) {
        array_multisort($weights, SORT_ASC, $items);
      }

      // For better markups, when Title option is not available at filters.
      if (empty($captions['title']) && isset($items['title'])) {
        $captions['title'] = $items['title'];
        unset($items['title']);
      }
      $captions['data'] = $items;
    }

    // Link, if so configured.
    if ($_link && isset($entity->{$_link})) {
      $links = $this->viewField($entity, $_link, $view_mode);
      $formatter = $links['#formatter'] ?? 'x';

      // Only simplify markups for known formatters registered by link.module.
      if ($links && in_array($formatter, ['link'])) {
        $links = [];
        foreach ($entity->{$_link} as $link) {
          $links[] = $link->view($view_mode);
        }
      }

      $blazies->set('field.values.link', $links);

      // If plain text or has no title, it is not worth a caption.
      if ($_switch == 'link' && $link = $links[0] ?? []) {
        if (Internals::emptyOrPlainTextLink($link)) {
          $links = [];
        }
      }

      $captions['link'] = $links;
    }

    return array_filter($captions);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPluginScopes(): array {
    $bundles  = $this->getAvailableBundles();
    $captions = $this->getFieldOptions();
    $_texts   = ['text', 'text_long', 'string', 'string_long', 'link'];
    $_links   = ['text', 'string', 'link'];
    $titles   = $this->getFieldOptions($_texts);
    $images   = [];
    $svg_form = static::$useSvg;

    if ($bundles) {
      $keys = array_keys($bundles);
      static::$useSvg = $svg_form || in_array('vector_image', $keys);

      // @todo figure out to not hard-code stock bundle image, vector_image.
      if (count(array_intersect($keys, ['image', 'vector_image'])) > 0) {
        $captions['title'] = $titles['title'] = $this->t('Image Title');
        $captions['alt'] = $this->t('Image Alt');
      }
    }

    // Only provides poster if media contains rich media.
    // @todo recheck without Image, Media loses image attribute association
    // due to core Media thumbnail returning NULL title value.
    // See https://www.drupal.org/project/blazy/issues/3390399
    // $media = BlazyDefault::imagePosters();
    // if (count(array_intersect($keys, $media)) > 0) {
    $images['images'] = $this->getFieldOptions(['image']);
    // }
    // @todo better way than hard-coding field name.
    unset(
      $captions['field_image'],
      $captions['field_media_image'],
      $captions['field_media']
    );

    return [
      'background'        => TRUE,
      'captions'          => $captions,
      'fieldable_form'    => TRUE,
      'image_style_form'  => TRUE,
      'media_switch_form' => TRUE,
      'svg_form'          => static::$useSvg,
      'multimedia'        => TRUE,
      'no_layouts'        => FALSE,
      'no_image_style'    => FALSE,
      'responsive_image'  => TRUE,
      'thumbnail_style'   => TRUE,
      'links'             => $this->getFieldOptions($_links),
      'titles'            => $titles,
    ] + $images
      + parent::getPluginScopes();
  }

  /**
   * Build extra elements.
   */
  protected function withElementExtra(array &$element): void {
    // @todo remove at 3.x:
    $entity = $element['#entity'] ?? NULL;
    $langcode = $element['#langcode'] ?? NULL;
    $this->buildElementExtra($element, $entity, $langcode);
  }

  /**
   * Build thumbnail navigation such as for Slick/ Splide asnavfor.
   *
   * @todo re-enable after sub-modules corrected params.
   *
   * Protected function withElementThumbnail(array &$build, array $element) {
   * Do nothing, let extenders do their jobs.
   * }
   */

  /**
   * Deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use
   *   self::getCaptions() instead.
   * @see https://www.drupal.org/node/3103018
   */
  protected function getCaption(array &$element, $entity, $langcode) {
    @trigger_error('getCaption is deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Field\BlazyEntityMediaBase::getCaptions() instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);
  }

  /**
   * Deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use
   *   self::withElementExtra() instead.
   * @see https://www.drupal.org/node/3103018
   */
  protected function buildElementExtra(array &$element, $entity, $langcode) {
    // @trigger_error('buildElementExtra is deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Field\BlazyEntityMediaBase::withElementExtra() instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use
   *   self::withElementDetail() instead.
   * @see https://www.drupal.org/node/3103018
   */
  protected function toElements(array &$build): void {
    @trigger_error('toElements is deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Field\BlazyEntityMediaBase::withElementDetail() instead. See https://www.drupal.org/node/3103018', E_USER_DEPRECATED);
  }

}
