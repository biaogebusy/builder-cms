<?php

namespace Drupal\social_api\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a Social Network item annotation object.
 *
 * @see \Drupal\social_api\Plugin\NetworkManager
 * @see plugin_api
 *
 * @Annotation
 */
class Network extends Plugin {

  /**
   * The module machine name.
   *
   * @var string
   */
  public string $id;

  /**
   * The social network service implemented by the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation|string
   *
   * @ingroup plugin_translatable
   */
  public Translation|string $socialNetwork;

  /**
   * The type of the plugin.
   *
   * @var string
   */
  public string $type;

  /**
   * Fully qualified Class name of the plugin SDK.
   *
   * @var string
   */
  public string $className;

  /**
   * A list of extra handlers.
   *
   * @var array
   *
   * @todo Check the entity type plugins to copy from.
   */
  public array $handlers = [];

}
