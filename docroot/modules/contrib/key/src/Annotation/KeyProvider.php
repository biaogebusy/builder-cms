<?php

namespace Drupal\key\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a key provider annotation object.
 *
 * @Annotation
 */
class KeyProvider extends Plugin {

  /**
   * The plugin ID of the key provider.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the key provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The description of the key provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The storage method of the key provider.
   *
   * This is an enumeration of {file, config, database, remote}.
   *
   * @var string
   *
   * @deprecated in key:1.18.0 and is removed from key:2.0.0. Use the 'tags'
   *   definition entry instead.
   *
   * @see https://www.drupal.org/node/3364701
   */
  public $storage_method;

  /**
   * The key provider tags, used for classification and filtering.
   *
   * It should be a list of tags as strings.
   *
   * @var array
   */
  public $tags = [];

  /**
   * The settings for inputting a key value.
   *
   * This is used to indicate to the key input plugin if this provider
   * accepts a key value and if it requires one.
   *
   * @var array
   */
  public $key_value = [
    'accepted' => FALSE,
    'required' => FALSE,
  ];

}
