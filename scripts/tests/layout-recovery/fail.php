<?php

declare(strict_types=1);

/**
 * @file
 * Injects an error after one insert in the isolated recovery fixture only.
 */

if (getenv('XINSHI_CHAT_ISOLATED_TEST') !== '1' || !is_file('/.dockerenv') || dirname(__DIR__, 3) !== '/app') {
  throw new RuntimeException('Use the disposable source-copy runner.');
}
$initialRows = \Drupal::database()->select('node__layout_builder__layout', 'l')->countQuery()->execute()->fetchField();
\Drupal::getContainer()->set('uuid', new class((int) $initialRows) extends \Drupal\Component\Uuid\Php {
  public function __construct(private int $initialRows) {}
  public function generate() {
    $rows = \Drupal::database()->select('node__layout_builder__layout', 'l')->countQuery()->execute()->fetchField();
    if ((int) $rows > $this->initialRows) { throw new RuntimeException('Injected recovery failure after insert'); }
    return parent::generate();
  }
});
require '/app/scripts/restore-panelizer-layouts.php';
