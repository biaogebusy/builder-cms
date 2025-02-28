<?php

namespace Drupal\entity_print\Plugin\EntityPrint\PrintEngine;

/**
 * An interface for print engines that support a header and footer.
 */
interface AlignableHeaderFooterInterface {

  /**
   * Align the text to the left.
   */
  const ALIGN_LEFT = 'left';

  /**
   * Align the text to the right.
   */
  const ALIGN_RIGHT = 'right';

  /**
   * Align the text in the center.
   */
  const ALIGN_CENTER = 'center';

  /**
   * Sets the header text.
   *
   * @param string $text
   *   The plain text to add to the header.
   * @param string $alignment
   *   One of the align constants.
   *
   * @return $this
   *   The pdf engine.
   */
  public function setHeaderText($text, $alignment);

  /**
   * Sets the footer text.
   *
   * @param string $text
   *   The plain text to add to the footer.
   * @param string $alignment
   *   One of the align constants.
   *
   * @return $this
   *   The pdf engine.
   */
  public function setFooterText($text, $alignment);

}
