<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;

/**
 * Kernel tests for MessageTemplate::preSave().
 *
 * @coversDefaultClass \Drupal\message\Entity\MessageTemplate
 *
 * @group Message
 */
class MessageTemplatePreSaveTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'message', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['filter', 'message']);
  }

  /**
   * Tests empty text partials are kept during config sync.
   *
   * @covers ::preSave
   */
  public function testPreSaveSkipsFilteringDuringConfigSync(): void {
    $text = [
      ['value' => 'Kept', 'format' => filter_default_format()],
      ['value' => '', 'format' => filter_default_format()],
    ];

    $this->container->get('config.installer')->setSyncing(TRUE);
    $template = MessageTemplate::create([
      'template' => 'sync_template',
      'label' => 'Sync template',
      'description' => 'Description',
      'text' => $text,
    ]);
    $template->save();
    $this->container->get('config.installer')->setSyncing(FALSE);

    $raw = $template->getRawText();
    $this->assertCount(2, $raw);
    $this->assertSame('Kept', $raw[0]['value']);
    $this->assertSame('', $raw[1]['value']);
  }

  /**
   * Tests empty text partials are filtered when not syncing.
   *
   * @covers ::preSave
   */
  public function testPreSaveFiltersEmptyPartialsWhenNotSyncing(): void {
    $template = MessageTemplate::create([
      'template' => 'filter_template',
      'label' => 'Filter template',
      'description' => 'Description',
      'text' => [
        ['value' => 'Kept', 'format' => filter_default_format()],
        ['value' => '', 'format' => filter_default_format()],
      ],
    ]);
    $template->save();

    $raw = $template->getRawText();
    $this->assertCount(1, $raw);
    $this->assertSame('Kept', array_values($raw)[0]['value']);
  }

}
