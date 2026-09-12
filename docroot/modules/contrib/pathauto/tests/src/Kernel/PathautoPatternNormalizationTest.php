<?php

namespace Drupal\Tests\pathauto\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\pathauto\Entity\PathautoPattern;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests pattern normalization in PathautoPattern::preSave().
 *
 * @group pathauto
 */
#[Group('pathauto')]
#[RunTestsInSeparateProcesses]
class PathautoPatternNormalizationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'text',
    'user',
    'node',
    'path',
    'path_alias',
    'pathauto',
    'token',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    if ($this->container->get('entity_type.manager')->hasDefinition('path_alias')) {
      $this->installEntitySchema('path_alias');
    }
    $this->installConfig(['pathauto', 'system', 'node']);
  }

  /**
   * Tests that patterns are normalized on save.
   */
  public function testPatternNormalization(): void {
    $cases = [
      // Trim leading/trailing spaces.
      ['  /content/[node:title]  ', '/content/[node:title]'],
      // Trim tabs.
      ["\t/content/[node:title]\t", '/content/[node:title]'],
      // Add missing leading slash.
      ['content/[node:title]', '/content/[node:title]'],
      // Existing leading slash unchanged.
      ['/content/[node:title]', '/content/[node:title]'],
      // Trim spaces and add leading slash.
      ['  content/[node:title]  ', '/content/[node:title]'],
      // Remove tabs in the middle.
      ["/content/\t[node:title]", '/content/[node:title]'],
      // Remove carriage returns and newlines.
      ["/content/\r\n[node:title]", '/content/[node:title]'],
    ];

    foreach ($cases as [$input, $expected]) {
      $id = $this->randomMachineName();
      $pattern = PathautoPattern::create([
        'id' => $id,
        'label' => 'Test pattern',
        'type' => 'canonical_entities:node',
        'pattern' => $input,
        'weight' => 0,
      ]);
      $pattern->save();

      // Reload from storage to confirm persisted value.
      $loaded = PathautoPattern::load($id);
      $this->assertSame($expected, $loaded->getPattern());
    }
  }

}
