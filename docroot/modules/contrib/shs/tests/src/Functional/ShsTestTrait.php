<?php

namespace Drupal\Tests\shs\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Base test class for SHS browser tests.
 *
 * @group shs
 */
trait ShsTestTrait {

  use TaxonomyTestTrait;
  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }

  /**
   * The vocabulary.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  protected $vocabulary;

  /**
   * The term IDs indexed by term names.
   *
   * @var int[]
   */
  protected $termIds;

  /**
   * Prepares test setup by creating a content type with the necessary fields.
   *
   * @param string $field_name
   *   Name of field to create.
   * @param int $cardinality
   *   The field cardinality.
   */
  protected function prepareSetup($field_name, int $cardinality = 1): void {
    // Create article content type.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    }

    // Create a vocabulary.
    $this->vocabulary = $this->createVocabulary();

    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => $cardinality,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'bundle' => 'article',
      'entity_type' => 'node',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            $this->vocabulary->id() => $this->vocabulary->id(),
          ],
        ],
      ],
    ])->save();
    // Configure the form widget.
    EntityFormDisplay::load('node.article.default')
      ->setComponent($field_name, [
        'type' => 'options_shs',
      ])
      ->save();
    // Configure display settings.
    EntityViewDisplay::load('node.article.default')
      ->setComponent($field_name, [
        'type' => 'entity_reference_label',
      ])
      ->save();

    // Create terms.
    $this->createTerms();
  }

  /**
   * Create terms for use in tests.
   */
  protected function createTerms(): void {
    $this->termIds = [];

    $terms = $this->getTermStructure();

    foreach ($terms as $name => $parent) {
      $values = [
        'name' => $name,
      ];
      if (!empty($parent) && isset($this->termIds[$parent])) {
        $values['parent'] = $this->termIds[$parent];
      }
      $term = $this->createTerm($this->vocabulary, $values);
      $this->termIds[$name] = $term->id();
    }
  }

  /**
   * Get the term structure used in tests.
   *
   * @return array
   *   List of terms having the key as the name of the term and the value as the
   *   name of the parent (empty string if there is no parent).
   */
  protected function getTermStructure(): array {
    return [
      'aaa 1' => '',
      'aaa 11' => 'aaa 1',
      'aaa 111' => 'aaa 11',
      'aaa 112' => 'aaa 11',
      'aaa 12' => 'aaa 1',
      'aaa 121' => 'aaa 12',
      'aaa 122' => 'aaa 12',
      'aaa 1221' => 'aaa 122',
      'aaa 12211' => 'aaa 1221',
      'aaa 12212' => 'aaa 1221',
      'aaa 12213' => 'aaa 1221',
      'aaa 12214' => 'aaa 1221',
      'aaa 1222' => 'aaa 122',
      'aaa 13' => 'aaa 1',
      'aaa 14' => 'aaa 1',
      'aaa 2' => '',
      'aaa 3' => '',
      'aaa 31' => 'aaa 3',
      'aaa 311' => 'aaa 31',
      'aaa 312' => 'aaa 31',
      'aaa 4' => '',
    ];
  }

  /**
   * Helper function for JSON formatted requests.
   *
   * @param string|\Drupal\Core\Url $path
   *   Drupal path or URL to load into Mink controlled browser.
   * @param array $options
   *   (optional) Options to be forwarded to the url generator.
   * @param string[] $headers
   *   (optional) An array containing additional HTTP request headers.
   *
   * @return string[]
   *   Array representing decoded JSON response.
   */
  protected function drupalGetJson($path, array $options = [], array $headers = []): array {
    $options_expanded = array_merge_recursive(['query' => ['_format' => 'json']], $options);
    return Json::decode($this->drupalGet($path, $options_expanded, $headers));
  }

}
