<?php

declare(strict_types=1);

namespace Drupal\private_message\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Annotation definition for the Private Message Configuration Form plugin.
 *
 * @Annotation
 */
class PrivateMessageConfigForm extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The name of the form plugin.
   *
   * @ingroup plugin_translatable
   */
  public Translation $name;

}
