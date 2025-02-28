<?php

namespace Drupal\entity_print;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_print\Plugin\PrintEngineInterface;

/**
 * Interface for the Print builder service.
 */
interface PrintBuilderInterface {

  /**
   * Render any content entity as a Print.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The content entity to render.
   * @param \Drupal\entity_print\Plugin\PrintEngineInterface $print_engine
   *   The plugin id of the Print engine to use.
   * @param bool $force_download
   *   (optional) TRUE to try and force the document download.
   * @param bool $use_default_css
   *   (optional) TRUE if you want the default CSS included, otherwise FALSE.
   *
   * @return string
   *   FALSE or the Print content will be sent to the browser.
   */
  public function deliverPrintable(array $entities, PrintEngineInterface $print_engine, $force_download = FALSE, $use_default_css = TRUE);

  /**
   * Get a HTML version of the entity as used for the Print rendering.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The content entity to render.
   * @param bool $use_default_css
   *   TRUE if you want the default CSS included, otherwise FALSE.
   * @param bool $optimize_css
   *   TRUE if you the CSS should be compressed otherwise FALSE.
   *
   * @return string
   *   The rendered HTML for the entity, the same as what is used for the Print.
   */
  public function printHtml(EntityInterface $entity, $use_default_css = TRUE, $optimize_css = TRUE);

  /**
   * Render any content entity as a printed document and save to disk.
   *
   * Be careful when not specifying a uri as the default behaviour will use the
   * default file scheme which is likely to be public and therefore putting a
   * rendered version of this entity in a web accessible location. If you want
   * to keep the files private, you must specify the uri yourself when calling
   * this method.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The content entity to render.
   * @param \Drupal\entity_print\Plugin\PrintEngineInterface $print_engine
   *   The plugin id of the Print engine to use.
   * @param string $scheme
   *   The Drupal scheme.
   * @param string $filename
   *   (optional) The filename or empty to have one generated.
   * @param bool $use_default_css
   *   (optional) TRUE if you want the default CSS included, otherwise FALSE.
   *
   * @return string
   *   FALSE or the URI to the file. E.g. public://my-file.pdf.
   */
  public function savePrintable(array $entities, PrintEngineInterface $print_engine, $scheme = 'public', $filename = '', $use_default_css = TRUE);

}
