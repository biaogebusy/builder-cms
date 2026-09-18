<?php

namespace Drupal\captcha\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\captcha\Constants\CaptchaConstants;

/**
 * Helper service for CAPTCHA module.
 */
class CaptchaService {

  use StringTranslationTrait;

  /**
   * Module Handler Service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructor for Captcha Service helper.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module Handler Service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Return an array with the available CAPTCHA types.
   *
   * For use as options array for a select form elements.
   *
   * @param bool $add_special_options
   *   If true: also add the 'default' option.
   *
   * @return array
   *   An associative array mapping "$module/$type" to
   *   "$type (from module $module)" with $module the module name
   *   implementing the CAPTCHA and $type the name of the CAPTCHA type.
   */
  public function getAvailableChallengeTypes(bool $add_special_options = TRUE) {
    $challenges = [];

    if ($add_special_options) {
      $challenges[CaptchaConstants::CAPTCHA_TYPE_DEFAULT] = $this->t('Default challenge type');
    }

    // We do our own version of Drupal's module_invoke_all() here because
    // we want to build an array with custom keys and values.
    $types = [];
    $this->moduleHandler->invokeAllWith('captcha', function (callable $hook, string $module) use (&$types) {
      if ($type = $hook('list')) {
        if (!is_array($type)) {
          $types[$module] = [$type];
        }
        else {
          $types[$module] = $type;
        }
      }
    });
    if (!empty($types)) {
      foreach ($types as $module => $values) {
        foreach ($values as $value) {
          $challenges["$module/$value"] = $this->t('@type (from module @module)', [
            '@type' => $value,
            '@module' => $module,
          ]);
        }
      }
    }

    return $challenges;
  }

  /**
   * Helper function to insert a CAPTCHA element before a given form element.
   *
   * @param array $form
   *   the form to add the CAPTCHA element to.
   * @param null|array $placement
   *   information where the CAPTCHA element should be inserted.
   *   $placement should be an associative array with fields:
   *     - 'path': path (array of path items) of the container in
   *       the form where the CAPTCHA element should be inserted.
   *     - 'key': the key of the element before which the CAPTCHA element
   *       should be inserted. If the field 'key' is undefined or NULL,
   *       the CAPTCHA will just be appended in the container.
   *     - 'weight': if 'key' is not NULL: should be the weight of the
   *       element defined by 'key'. If 'key' is NULL and weight is not NULL:
   *       set the weight property of the CAPTCHA element to this value.
   * @param array $captcha_element
   *   the CAPTCHA element to insert.
   */
  public function insertCaptchaElement(array &$form, ?array $placement = NULL, ?array $captcha_element = NULL) {
    // Get path, target and target weight or use defaults if not available.
    $target_key = $placement['key'] ?? NULL;
    $target_weight = $placement['weight'] ?? NULL;
    $path = $placement['path'] ?? [];

    // Walk through the form along the path.
    $form_stepper = &$form;
    foreach ($path as $step) {
      if (isset($form_stepper[$step])) {
        $form_stepper = &$form_stepper[$step];
      }
      else {
        // Given path is invalid: stop stepping and
        // continue in best effort (append instead of insert).
        $target_key = NULL;
        break;
      }
    }

    // If no target is available: just append the CAPTCHA element
    // to the container.
    if ($target_key == NULL || !array_key_exists($target_key, $form_stepper)) {
      // Optionally, set weight of CAPTCHA element.
      if ($target_weight != NULL) {
        $captcha_element['#weight'] = $target_weight;
      }
      $form_stepper['captcha'] = $captcha_element;
    }
    // If there is a target available: make sure the CAPTCHA element
    // comes right before it.
    else {
      // If target has a weight: set weight of CAPTCHA element a bit smaller
      // and just append the CAPTCHA: sorting will fix the ordering anyway.
      if ($target_weight != NULL) {
        $captcha_element['#weight'] = $target_weight - .1;
        $form_stepper['captcha'] = $captcha_element;
      }
      else {
        // If we can't play with weights: insert the CAPTCHA element
        // at the right position. Because PHP lacks a function for
        // this (array_splice() comes close, but it does not preserve
        // the key of the inserted element), we do it by hand: chop of
        // the end, append the CAPTCHA element and put the end back.
        $offset = array_search($target_key, array_keys($form_stepper));
        $end = array_splice($form_stepper, $offset);
        $form_stepper['captcha'] = $captcha_element;
        foreach ($end as $k => $v) {
          $form_stepper[$k] = $v;
        }
      }

    }
  }

}
