<?php

/**
 * @file
 * Theme functions for the menu_item_extras module.
 */

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;

/**
 * Prepares variables for menu link content templates.
 *
 * Default template: menu-link-content.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - menu_link_content: An individual menu_link_content for display.
 */
function template_preprocess_menu_link_content(array &$variables) {
  $show_item_link = TRUE;
  /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_link_content */
  $menu_link_content = $variables['elements']['#menu_link_content'];
  $variables['menu_link_content'] = $menu_link_content;
  // Disabled standard entity render for using custom TWIG render.
  unset($variables['elements']['#pre_render']);
  // Helpful $content variable for templates.
  $variables['content'] = $variables['elements'];

  $variables['attributes']['class'][] = 'menu_link_content';
  $variables['attributes']['class'][] = Html::getClass('menu_link_content' . $menu_link_content->getMenuName());
  if ($variables['elements']['#view_mode']) {
    $variables['attributes']['class'][] = Html::getClass('view-mode-' . $variables['elements']['#view_mode']);
  }

  if (isset($variables['elements']['#show_item_link'])) {
    $show_item_link = $variables['elements']['#show_item_link'];
  }

  $variables['show_item_link'] = $show_item_link;
}

/**
 * Implements hook_preprocess_menu_levels().
 */
function template_preprocess_menu_levels(array &$variables) {
  $variables += ['items' => []];

  if (!empty($variables['element']['#items'])) {
    $items = $variables['element']['#items'];
    $variables['menu_name'] = $variables['element']['#menu_name'];
    $variables['menu_level'] = $variables['element']['#menu_level'];

    foreach (Element::children($items) as $key) {
      $variables['items'][$key] = $items[$key];
    }
  }
}
