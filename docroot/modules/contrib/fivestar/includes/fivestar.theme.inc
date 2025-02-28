<?php

/**
 * @file
 * Provides the theming functions for fivestar.
 */

use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElement;

/**
 * This is dead code in Drupal 10.
 */
function theme_fivestar_preview($variables) {
  extract($variables, EXTR_SKIP);
  $values = [
    'vote_average' => 50,
    'vote_user' => 80,
    'vote_count' => 20,
  ];
  $settings = [
    'stars' => $stars,
    'allow_clear' => $unvote,
    'allow_revote' => $revote,
    'allow_ownvote' => $ownvote,
    'style' => $style,
    'text' => $text,
    'title' => $title,
    'autosubmit' => FALSE,
    'vote_type' => 'vote',
  ];

  $form = drupal_get_form('fivestar_custom_widget', $values, $settings);
  $form = \Drupal::service('renderer')->render($form);
  // This regex is sadly necessary because having duplicate form_tokens or
  // form_id elements can cause the content type form to choke. Forms inside of
  // forms is also frowned upon, so this removes the wrapping form tag as well.
  $form = str_replace(['<form', '</form>'], [
    '<div',
    '</div>',
  ], $form);
  $form = preg_replace('/( method=".*?")|( action=".*?")|(<input.*?name="(form_token|form_id|destination|form_build_id)".*?\/>)/', '', $form);
  return $form;
}
