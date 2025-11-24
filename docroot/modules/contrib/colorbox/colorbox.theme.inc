<?php

/**
 * @file
 * Colorbox theme functions.
 */

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Xss;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\Component\Utility\Html;

/**
 * Prepares variables for colorbox formatter templates.
 *
 * Default template: colorbox-formatter.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - item: An ImageItem object.
 *   - item_attributes: An optional associative array of html attributes to be
 *     placed in the img tag.
 *   - entity: An entity object.
 *   - settings: Formatter settings array.
 */
function template_preprocess_colorbox_formatter(&$variables) {
  $item = $variables['item'];
  $item_attributes = $variables['item_attributes'] ?? [];
  $entity = $variables['entity'];
  $settings = $variables['settings'];
  $image_uri = $item->entity->getFileUri();
  $classes_array = ['colorbox'];
  $data_cbox_img_attrs = [];

  // Build the caption.
  $entity_title = $entity->label();
  $entity_type = $entity->getEntityTypeId();

  switch ($settings['colorbox_caption']) {
    case 'auto':
      // If the title is empty use alt or the entity title in that order.
      if (!empty($item->title)) {
        $caption = $item->title;
      }
      elseif (!empty($item->alt)) {
        $caption = $item->alt;
      }
      elseif (!empty($entity_title)) {
        $caption = $entity_title;
      }
      else {
        $caption = '';
      }
      break;

    case 'title':
      $caption = $item->title ?? '';
      break;

    case 'alt':
      $caption = $item->alt ?? '';
      break;

    case 'entity_title':
      $caption = $entity_title;
      break;

    case 'custom':
      $token_service = \Drupal::token();
      $caption = $token_service->replace(
        $settings['colorbox_caption_custom'],
        [$entity_type => $entity, 'file' => $item],
        ['clear' => TRUE]
      );
      break;

    default:
      $caption = '';
  }

  // Shorten the caption for the example styles or when caption
  // shortening is active.
  $config = \Drupal::config('colorbox.settings');
  $colorbox_style = !empty($config->get('colorbox_style')) ? $config->get('colorbox_style') : '';
  $trim_length = $config->get('colorbox_caption_trim_length');
  if (((strpos($colorbox_style, 'colorbox/example') !== FALSE) || $config->get('colorbox_caption_trim')) && (strlen($caption) > $trim_length)) {
    $caption = substr($caption, 0, $trim_length - 5) . '...';
  }

  $gallery_id = \Drupal::service('colorbox.gallery_id_generator')->generateId($entity, $item, $settings);

  // Set up the $variables['image'] parameter.
  if ($settings['style_name'] == 'hide') {
    $variables['image'] = [];
    $classes_array[] = 'js-hide';
  }
  elseif (!empty($settings['style_name'])) {
    $variables['image'] = [
      '#theme' => 'image_style',
      '#style_name' => $settings['style_name'],
    ];
  }
  else {
    $variables['image'] = [
      '#theme' => 'image',
    ];
  }

  if (!empty($variables['image'])) {
    $variables['image']['#attributes'] = $item_attributes;

    // Do not output an empty 'title' attribute.
    if (!empty($item->title)) {
      $variables['image']['#title'] = $item->title;
      $data_cbox_img_attrs['title'] = '"title":"' . $item->title . '"';
    }

    foreach (['width', 'height', 'alt'] as $key) {
      $variables['image']["#$key"] = $item->$key;
      if ($key == 'alt') {
        $data_cbox_img_attrs['alt'] = '"alt":"' . $item->alt . '"';
      }
    }

    $variables['image']['#uri'] = empty($item->uri) ? $image_uri : $item->uri;
  }

  if (!empty($settings['colorbox_image_style'])) {
    $style = ImageStyle::load($settings['colorbox_image_style']);
    $variables['url'] = $style->buildUrl($image_uri);
  }
  else {
    /** @var \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator */
    $file_url_generator = \Drupal::service('file_url_generator');

    $variables['url'] = $file_url_generator->generateAbsoluteString($image_uri);
  }
  $unique_id = 'colorbox-' . Crypt::randomBytesBase64(8);
  $variables['attributes']['title'] = _colorbox_formatter_get_caption($variables);
  $variables['attributes']['data-colorbox-gallery'] = $gallery_id;
  $variables['attributes']['class'] = $classes_array;
  $variables['attributes']['aria-controls'] = $unique_id;
  $variables['image']['#attributes']['id'] = $unique_id;
  if (!empty($data_cbox_img_attrs)) {
    $variables['attributes']['data-cbox-img-attrs'] = '{' . implode(',', $data_cbox_img_attrs) . '}';
  }
}

/**
 * Prepares variables for colorbox responsive formatter templates.
 *
 * Default template: colorbox-responsive-formatter.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - item: An ImageItem object.
 *   - item_attributes: An optional associative array of html attributes to be
 *     placed in the img tag.
 *   - entity: An entity object.
 *   - settings: Formatter settings array.
 */
function template_preprocess_colorbox_responsive_formatter(array &$variables) {
  $colorbox_inline = \Drupal::moduleHandler()->moduleExists('colorbox_inline');

  $item = $variables['item'];
  $item_attributes = $variables['item_attributes'] ?? [];
  $entity = $variables['entity'];
  $entity_bundle = $entity->bundle();
  $id = $entity->id();
  $entity_id = !empty($id) ? $entity_bundle . '-' . $id : 'entity-id';

  $settings = $variables['settings'];
  $image_uri = $item->entity->getFileUri();
  $classes_array = ['colorbox'];
  $data_cbox_img_attrs = [];

  $responsive_style = NULL;
  if (!empty($settings['colorbox_responsive_node_style'])) {
    $responsive_style = ResponsiveImageStyle::load($settings['colorbox_responsive_node_style']);
  }

  // Set up the $variables['responsive_image'] parameter.
  if ($settings['colorbox_responsive_node_style'] == 'hide') {
    $variables['responsive_image'] = [];
    $classes_array[] = 'js-hide';
  }
  elseif ($responsive_style) {
    $variables['responsive_image'] = [
      '#theme' => 'responsive_image',
      '#responsive_image_style_id' => $settings['colorbox_responsive_node_style'],
    ];
  }
  else {
    $variables['responsive_image'] = [
      '#theme' => 'image',
    ];
  }

  if (!empty($variables['responsive_image'])) {
    $attributes = [];
    // Do not output an empty 'title' attribute.
    if (mb_strlen($item->title ?? '') != 0) {
      $variables['responsive_image']['#title'] = $item->title;
      $data_cbox_img_attrs['title'] = '"title":"' . $item->title . '"';
      $attributes['title'] = $item->title;
    }

    foreach (['width', 'height', 'alt'] as $key) {
      $variables['responsive_image']["#$key"] = $item->$key;
    }
    $data_cbox_img_attrs['alt'] = '"alt":"' . $item->alt . '"';
    $attributes['alt'] = $item->alt;

    $variables['responsive_image']['#uri'] = empty($item->uri) ? $image_uri : $item->uri;
    $variables['responsive_image']['#attributes'] = $attributes + $item_attributes;
  }

  $responsive_style = NULL;
  if (!empty($settings['colorbox_responsive_image_style'])) {
    $responsive_style = ResponsiveImageStyle::load($settings['colorbox_responsive_image_style']);
  }

  // Since responsive images don't have an external url, link to internal
  // content.
  if ($colorbox_inline && $responsive_style) {
    // Create a unique internal link for the picture tag.
    // We use a short token since randomness is not critical.
    $image_id = $entity_id . '-' . Crypt::randomBytesBase64(8);
    $variables['image_id'] = $image_id;
    $variables['url'] = '';
    $variables['responsive_image'] = [
      '#theme' => 'responsive_image',
      '#responsive_image_style_id' => $settings['colorbox_responsive_image_style'],
      '#uri' => empty($item->uri) ? $image_uri : $item->uri,
      '#width' => $item->width,
      '#height' => $item->height,
    ];
    $variables['attributes']['data-colorbox-inline'] = '#' . $image_id;
  }
  if (!empty($settings['colorbox_image_style'])) {
    $style = ImageStyle::load($settings['colorbox_image_style']);
    $variables['url'] = $style->buildUrl($image_uri);
  }
  else {
    /** @var \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator */
    $file_url_generator = \Drupal::service('file_url_generator');

    $variables['url'] = $file_url_generator->generateAbsoluteString($image_uri);
  }

  $gallery_id = \Drupal::service('colorbox.gallery_id_generator')->generateId($entity, $item, $settings);

  $variables['attributes']['title'] = _colorbox_formatter_get_caption($variables);
  $variables['attributes']['data-colorbox-gallery'] = $gallery_id;
  $variables['attributes']['class'] = $classes_array;
  if (!empty($data_cbox_img_attrs)) {
    $variables['attributes']['data-cbox-img-attrs'] = '{' . implode(',', $data_cbox_img_attrs) . '}';
  }
}

/**
 * Prepares variables for colorbox view modes formatter template.
 *
 * Default template: colorbox-view-mode-formatter.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - item: A entity object.
 *   - content: A rendered entity view mode to display in the content.
 *   - modal: A rendered entity view mode to display in the colorbox modal
 *   - item_attributes: An optional associative array of html attributes to be
 *     placed on the colorbox anchor.
 *   - entity: An entity object.
 *   - settings: Formatter settings array.
 */
function template_preprocess_colorbox_view_mode_formatter(array &$variables) {
  static $gallery_token = NULL;

  /** @var \Drupal\Core\Entity\EntityInterface $item */
  $item = $variables['item'];
  /** @var \Drupal\Core\Entity\EntityInterface $entity */
  $entity = $variables['entity'];
  $field_name = $variables['field_name'];
  $settings = $variables['settings'];

  $config = \Drupal::config('colorbox.settings');

  // Build the gallery id.
  $entity_type = $entity->getEntityTypeId();
  $entity_bundle = $entity->bundle();
  $id = $entity->id();
  $entity_id = !empty($id) ? $entity_bundle . '-' . $id : 'entity-id';

  switch ($settings['colorbox_gallery']) {
    case 'post':
      $gallery_id = 'gallery-' . $entity_id;
      break;

    case 'page':
      $gallery_id = 'gallery-all';
      break;

    case 'field_post':
      $gallery_id = 'gallery-' . $entity_id . '-' . $field_name;
      break;

    case 'field_page':
      $gallery_id = 'gallery-' . $field_name;
      break;

    case 'custom':
      $token_service = \Drupal::token();
      $gallery_id = $token_service->replace($settings['colorbox_gallery_custom'],
        [$entity_type => $entity, 'entity' => $item], ['clear' => TRUE]);
      break;

    default:
      $gallery_id = '';
  }

  // If gallery id is not empty add unique per-request token to avoid
  // images being added manually to galleries.
  if (!empty($gallery_id) && $config->get('advanced.unique_token')) {
    // Check if gallery token has already been set, we need to reuse
    // the token for the whole request.
    if ($gallery_token === NULL) {
      // We use a short token since randomness is not critical.
      $gallery_token = Crypt::randomBytesBase64(8);
    }
    $gallery_id = $gallery_id . '-' . $gallery_token;
  }

  $variables['url'] = NULL;

  $variables['attributes']['title'] = $item->label();
  $variables['attributes']['data-colorbox-gallery'] = $gallery_id;
  $variables['attributes']['class'] = ['colorbox'];
}

/**
 * Get the caption for an image.
 *
 * @param array $variables
 *   An associative array containing:
 *   - item: An ImageItem object.
 *   - item_attributes: An optional associative array of html attributes to be
 *     placed in the img tag.
 *   - entity: An entity object.
 *   - settings: Formatter settings array.
 *
 * @return string
 *   The caption text of the image parameter.
 */
function _colorbox_formatter_get_caption(&$variables) {
  $item = $variables['item'];
  $entity = $variables['entity'];
  $settings = $variables['settings'];

  // Build the caption.
  $entity_title = $entity->label();
  $entity_type = $entity->getEntityTypeId();

  switch ($settings['colorbox_caption']) {
    case 'auto':
      // If the title is empty use alt or the entity title in that order.
      if (!empty($item->title)) {
        $caption = $item->title;
      }
      elseif (!empty($item->alt)) {
        $caption = $item->alt;
      }
      elseif (!empty($entity_title)) {
        $caption = $entity_title;
      }
      else {
        $caption = '';
      }
      break;

    case 'title':
      $caption = $item->title;
      break;

    case 'alt':
      $caption = $item->alt;
      break;

    case 'entity_title':
      $caption = $entity_title;
      break;

    case 'custom':
      $token_service = \Drupal::token();
      $caption = $token_service->replace(
        $settings['colorbox_caption_custom'],
        [$entity_type => $entity, 'file' => $item],
        ['clear' => TRUE]
      );
      break;

    default:
      $caption = '';
  }

  // If File Entity module is enabled, load attribute values from file entity.
  if (\Drupal::moduleHandler()->moduleExists('file_entity')) {
    // File id of the save file.
    $fid = $item->target_id;
    // Load file object.
    $file_obj = File::load($fid);
    $file_array = $file_obj->toArray();
    // Populate the image title.
    if (!empty($file_array['field_image_title_text'][0]['value']) && empty($item->title) && $settings['colorbox_caption'] == 'title') {
      $caption = $file_array['field_image_title_text'][0]['value'];
    }
    // Populate the image alt text.
    if (!empty($file_array['field_image_alt_text'][0]['value']) && empty($item->alt) && $settings['colorbox_caption'] == 'alt') {
      $caption = $file_array['field_image_alt_text'][0]['value'];
    }
  }

  // Shorten the caption for the example styles or when caption
  // shortening is active.
  $config = \Drupal::config('colorbox.settings');
  $colorbox_style = !empty($config->get('colorbox_style')) ? $config->get('colorbox_style') : '';
  $trim_length = $config->get('colorbox_caption_trim_length');
  if (((strpos($colorbox_style, 'colorbox/example') !== FALSE) || $config->get('colorbox_caption_trim')) && (strlen($caption) > $trim_length)) {
    $caption = substr($caption, 0, $trim_length - 5) . '...';
  }
  return Html::decodeEntities(Xss::filter($caption ?? ''));
}
