<?php

namespace Drupal\layout_builder_st\Plugin\DataType;

use Drupal\Core\TypedData\TypedData;

/**
 * Provides a data type wrapping \Drupal\layout_builder\Section.
 *
 * @DataType(
 *   id = "layout_translation",
 *   label = @Translation("Layout translation"),
 *   description = @Translation("A layout translation"),
 * )
 */
final class LayoutTranslationData extends TypedData {

  /**
   * The layout translation.
   *
   * @var array
   */
  protected $value;

}
