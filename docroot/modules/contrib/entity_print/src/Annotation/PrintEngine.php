<?php

namespace Drupal\entity_print\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * The PrintEngine annotation.
 *
 * @Annotation
 */
class PrintEngine extends Plugin {

  /**
   * The unique Id of the Print engine implementation.
   *
   * @var string
   */
  public $id;

  /**
   * The human readable name of the Print engine implementation.
   *
   * @var string
   */
  public $label;

  /**
   * The filetype to be exported to.
   *
   * @var string
   *
   * @codingStandardsIgnoreStart
   */
  public $export_type;

}
