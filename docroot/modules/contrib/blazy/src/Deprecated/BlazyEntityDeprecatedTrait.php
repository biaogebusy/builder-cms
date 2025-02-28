<?php

namespace Drupal\blazy\Deprecated;

use Drupal\blazy\Field\BlazyField;
use Drupal\blazy\internals\Internals;

/**
 * Deprecated in blazy:8.x-2.9.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 *
 * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
 *   \Drupal\blazy\Field\BlazyField methods instead.
 * @see https://www.drupal.org/node/3367291
 */
trait BlazyEntityDeprecatedTrait {

  /**
   * Deprecated method to return the entity renderable array.
   *
   * No more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   self::view() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getEntityView($entity, array $settings = [], $fallback = '') {
    @trigger_error('getEntityView is deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use self::view() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    $data = [
      '#entity'   => $entity,
      '#settings' => $settings,
      'fallback'  => $fallback,
    ];
    return $this->view($data);
  }

  /**
   * Deprecated method to return the field renderable array.
   *
   * No more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::view() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getFieldRenderable($entity, $field_name, $view_mode, $multiple = TRUE) {
    @trigger_error('getFieldRenderable is deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use \Drupal\blazy\Field\BlazyField::view() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return BlazyField::view($entity, $field_name, $view_mode, $multiple);
  }

  /**
   * Deprecated method to return the string value of link, or text.
   *
   * No more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getString() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getFieldString($entity, $field_name, $langcode, $clean = TRUE) {
    @trigger_error('getFieldString is deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use \Drupal\blazy\Field\BlazyField::getString() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return BlazyField::getString($entity, $field_name, $langcode, $clean);
  }

  /**
   * Deprecated method to return the text or link value.
   *
   * No more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getTextOrLink() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getFieldTextOrLink($entity, $field_name, $settings, $multiple = TRUE) {
    @trigger_error('getFieldTextOrLink is deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use \Drupal\blazy\Field\BlazyField::getTextOrLink() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    $langcode  = $settings['langcode'] ?? '';
    $view_mode = $settings['view_mode'] ?? 'default';
    return BlazyField::getTextOrLink($entity, $field_name, $view_mode, $langcode, $multiple);
  }

  /**
   * Deprecated method to return the string value of link, or text.
   *
   * No more called by sub-modules.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   BlazyField::getValue() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getFieldValue($entity, $field_name, $langcode) {
    @trigger_error('getFieldValue is deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use \Drupal\blazy\Field\BlazyField::getValue() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return BlazyField::getValue($entity, $field_name, $langcode);
  }

  /**
   * Deprecated method to return file view or media.
   *
   * @todo deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use
   *   none instead.
   * @see https://www.drupal.org/node/3367291
   */
  public function getFileOrMedia($file, array $settings, $rendered = TRUE) {
    @trigger_error('getFileOrMedia is deprecated in blazy:8.x-2.9 and is removed from blazy:3.0.0. Use BlazyMedia::view() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    $data = [
      '#entity' => $file,
      '#settings' => $settings,
    ];
    if ($manager = Internals::service('blazy.media')) {
      return $rendered ? $manager->view($data) : $manager->fromFile($data);
    }
    return $rendered ? [] : NULL;
  }

}
