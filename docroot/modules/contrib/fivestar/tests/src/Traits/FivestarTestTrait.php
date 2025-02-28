<?php

namespace Drupal\Tests\fivestar\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Utility methods for using Fivestar in Functional and Functional JS tests.
 *
 * @group Fivestar
 */
trait FivestarTestTrait {

  /**
   * Creates a fivestar field and storage, and adds it to a content type.
   *
   * @param array $options
   *   (optional) An associative array of options for the field and instance.
   *   The keys can be one or more of:
   *   - content_type: Defaults to 'test_node_type'.
   *   - widget_type: Defaults to 'stars'.
   *   - display: Defaults to an empty array.
   */
  protected function createFivestarField(array $options = []): void {
    $options = $options + [
      'content_type' => 'test_node_type',
      'widget_type' => 'stars',
      'display' => [],
    ];

    // Define and create the storage.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'fivestar_test',
      'entity_type' => 'node',
      'type' => 'fivestar',
      'cardinality' => 1,
      'settings' => [
        'axis' => 'vote',
      ],
    ]);
    $field_storage->save();

    // Define and create the field.
    $field = FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => $field_storage->getName(),
      'label' => 'Fivestar test field',
      'bundle' => $options['content_type'],
      'widget' => [
        'type' => $options['widget_type'],
        'settings' => [
          'widget' => [
            'fivestar_widget' => 'default',
          ],
        ],
      ],
      'settings' => [
        'axis' => 'vote',
        'stars' => '5',
      ],
      'display' => $options['display'],
    ]);
    $field->save();
  }

  /**
   * Assert Fivestar rating output.
   *
   * @param string $rating
   *   The Fivestar rating to assert, e.g. 1, 2, 3, etc.
   */
  protected function assertFivestarRatingOutput(string $rating): void {
    // The Fivestar value is output as text in the first star.
    $this->assertSession()->elementTextContains('css', '.js-form-type-fivestar .star-first', $rating);
    // Average rating should also be output.
    $this->assertSession()->pageTextContains(sprintf('Average: %s (1 vote)', $rating));
  }

  /**
   * Assert Fivestar voting available.
   */
  protected function assertFivestarVotingAvailable(): void {
    // Voting is available when the "vote" select element is present.
    $this->assertSession()->elementExists('css', 'form.fivestar-widget select[name="vote"]');
  }

  /**
   * Assert Fivestar voting not available.
   */
  protected function assertFivestarVotingNotAvailable(): void {
    // Voting is not available when the "vote" select element is not present.
    $this->assertSession()->elementNotExists('css', 'form.fivestar-widget select[name="vote"]');
  }

}
