<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Functional;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Diff module plugins for Date Range field type.
 */
#[Group('diff')]
#[RunTestsInSeparateProcesses]
class DiffPluginDateRangeTest extends DiffPluginTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['datetime_range'];

  /**
   * Tests the Core plugin with a daterange field.
   *
   * @covers \Drupal\diff\Plugin\diff\Field\CoreFieldBuilder
   */
  public function testCorePluginDateRangeField(): void {
    // Add a daterange field (supported by the Diff core plugin) to the Article
    // content type.
    $field_name = 'field_date_range';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'daterange',
      'settings' => [
        'datetime_type' => 'date',
      ],
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'label' => 'Date range test',
    ])->save();

    // Add the daterange field to the article form.
    $this->loadFormDisplay('node.article.default')
      ->setComponent($field_name, ['type' => 'daterange_default'])
      ->save();

    // Add the daterange field to the default display.
    $this->loadViewDisplay('node.article.default')
      ->setComponent($field_name, ['type' => 'daterange_default'])
      ->save();

    $old_daterange = [
      'value' => (new DrupalDateTime('-1 year'))->format('Y-m-d'),
      'end_value' => (new DrupalDateTime('-1 month'))->format('Y-m-d'),
    ];
    $new_daterange = [
      'value' => (new DrupalDateTime('-1 week'))->format('Y-m-d'),
      'end_value' => (new DrupalDateTime('-1 day'))->format('Y-m-d'),
    ];

    // Create an article with a daterange.
    $node = $this->drupalCreateNode([
      'title' => 'daterange_test',
      'type' => 'article',
      $field_name => $old_daterange,
    ]);

    // Create a new revision with an updated daterange.
    $node->set($field_name, $new_daterange);
    $node->setNewRevision(TRUE);
    $node->save();

    // Compare the revisions.
    $this->drupalGet($node->toUrl('version-history'));
    $this->submitForm([], 'Compare selected revisions');

    // Assert that the page shows a formatted daterange field.
    $this->assertSession()->pageTextContains('Date range test');
    $date_formatter = \Drupal::service('date.formatter');
    $this->assertSession()->pageTextContains($date_formatter->format(\strtotime('-1 year'), 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT));
    $this->assertSession()->pageTextContains($date_formatter->format(\strtotime('-1 month'), 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT));
    $this->assertSession()->pageTextContains($date_formatter->format(\strtotime('-1 week'), 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT));
    $this->assertSession()->pageTextContains($date_formatter->format(\strtotime('-1 day'), 'custom', DateTimeItemInterface::DATE_STORAGE_FORMAT));
  }

}
