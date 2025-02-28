<?php

namespace Drupal\entity_print;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Extended PhpWord object for Entity print module.
 */
class EntityPrintPHPWord extends PhpWord
{

  /**
   * Returns printed raw printed content.
   *
   * @param string $format
   *   Exported format.
   *
   * @return string
   *  Printed content.
   */
  public function getBlob($format = 'Word2007')
  {
    $writer = IOFactory::createWriter($this, $format);
    ob_start();
    $writer->save('php://output');
    return ob_get_clean();
  }

}
