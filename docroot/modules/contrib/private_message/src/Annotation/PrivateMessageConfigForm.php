<?php

namespace Drupal\private_message\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotation definition for the Private Message Configuration Form plugin.
 *
 * @Annotation
 */
class PrivateMessageConfigForm extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the form plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

}
