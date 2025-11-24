<?php

namespace Drupal\ckeditor_templates;

/**
 * Interface for ckeditor_template plugins.
 */
interface CkeditorTemplateInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   *
   * @return string
   *   The translated title.
   */
  public function getDescription(): string;

  /**
   * Gets the thumbnail for an image.
   *
   * @return string
   *   The thumb image URL.
   */
  public function getThumb(): string;

  /**
   * Get a list of allowed text formats for the template.
   *
   * @return array
   *   The array of formats.
   */
  public function allowedFormats(): array;

  /**
   * Get the HTML to place in the Editor.
   *
   * @return string
   *   The HTML code.
   */
  public function getHtml(): string;

}
