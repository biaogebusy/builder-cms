<?php

/**
 * @file
 * Preprocessors and helper functions to make theming easier.
 */

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

/**
 * Prepares variables for wechat response templates.
 *
 * Default template: views-view-wechat.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - view: A ViewExecutable object.
 *   - rows: The raw row data.
 */
function template_preprocess_views_view_wechat(&$variables) {
  $view  = $variables['view'];
  $items = $variables['rows'];
  $variables['items'] = $items;
  //drupal_set_message('123456');
}

/**
 * Prepares variables for wechat response item templates.
 *
 * Default template: views-view-row-wechat.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - row: The raw results rows.
 */
function template_preprocess_views_view_row_wechat(&$variables) {
  $item = $variables['row'];
  $variables['title'] = $item->title;
  $variables['description'] = $item->description;
  $variables['pic_url'] = $item->pic_url;
  $variables['url'] = $item->url;
}