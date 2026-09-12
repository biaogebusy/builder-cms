<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;

/**
 * Test that we can assign a custom analyzer to a field and it works.
 *
 * @group elasticsearch_connector
 */
class CustomAnalyzerBackendTest extends BrowserTestBase {
  use ElasticsearchTestViewTrait;
  use ExampleContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['elasticsearch_connector_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The index we will use during this test.
   *
   * @var \Drupal\search_api\Entity\Index
   */
  protected Index $index;

  /**
   * The Search API Server backend we will use during this test.
   *
   * @var \Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend
   */
  protected ElasticSearchBackend $serverBackend;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup: Set up the example content structure and add some example content.
    $this->setUpExampleStructure();
    $this->insertExampleContent();

    // Setup: Re-index content in the test_elasticsearch_index.
    $numberIndexed = $this->indexItems(self::getIndexId());
    $this->assertEquals(\count($this->entities), $numberIndexed, 'The number of items indexed should match the number of items inserted.');

    // Setup: Prepare the Search API Index and Server Backend for the test.
    $this->index = Index::load(self::getIndexId());
    $backend = $this->index->getServerInstance()->getBackend();
    $this->assertInstanceOf(ElasticSearchBackend::class, $backend);
    $this->serverBackend = $backend;
  }

  /**
   * Test that a custom analyzer can be defined and functions properly.
   */
  public function testCustomAnalyzer(): void {
    // SUT: Load the index settings from the backend.
    $indexSettings = $this->serverBackend->getRawIndexSettings($this->index);

    // Assert: The custom analyzer should be defined in the index as we
    // specified it in the AnalyzerOne plugin, plus the extra 'trim' filter we
    // added in the event listener.
    $this->assertEqualsCanonicalizing(
      ['lowercase', 'english_stop', 'trim'],
      $indexSettings['test_elasticsearch_index']['settings']['index']['analysis']['analyzer']['elasticsearch_connector_test_analyzer_one']['filter'],
    );
    $this->assertEqualsCanonicalizing(
      ['emoticons'],
      $indexSettings['test_elasticsearch_index']['settings']['index']['analysis']['analyzer']['elasticsearch_connector_test_analyzer_one']['char_filter'],
    );
    $this->assertEquals('punctuation', $indexSettings['test_elasticsearch_index']['settings']['index']['analysis']['analyzer']['elasticsearch_connector_test_analyzer_one']['tokenizer']);

    // SUT: Load the index mappings from the backend.
    $indexMappings = $this->serverBackend->getRawIndexMappings($this->index);

    // Assert: The custom analyzer should be attached to the
    // 'body_with_custom_analyzer' field.
    $this->assertEquals('elasticsearch_connector_test_analyzer_one', $indexMappings['test_elasticsearch_index']['mappings']['properties']['body_with_custom_analyzer']['analyzer']);

    // SUT: Run the analyzer on a test phrase.
    //
    // See
    // https://www.elastic.co/docs/manage-data/data-store/text-analysis/create-custom-analyzer#:~:text=Here%20is%20a%20more%20complicated%20example
    // for the test phrase and expected output.
    // Note that we altered the example from the docs to add the 'trim' token
    // filter in
    // \Drupal\elasticsearch_connector_test\EventSubscriber\AnalyzerAssignmentSubscriber::onAlterIndexSettings()
    // but this should not affect the result.
    $answer0 = $this->serverBackend->runIndexAnalyzer($this->index, 'elasticsearch_connector_test_analyzer_one', "I'm a :) person, and you?");

    // Assert: After reducing the token details to just the extracted tokens, we
    // should get the example from the docs.
    $answer0TokensOnly = \array_map(fn ($tokenDetail) => $tokenDetail['token'], $answer0['tokens']);
    $this->assertEquals(["i'm", "_happy_", "person", "you"], $answer0TokensOnly);
  }

  /**
   * Test the querytime_synonyms analyzer.
   *
   * @see \Drupal\elasticsearch_connector\Event\SynonymsSubscriber::onAlterSettings()
   */
  public function testQueryTimeSynonymsAnalyzer(): void {
    // SUT: Load the index settings from the backend.
    $indexSettings = $this->serverBackend->getRawIndexSettings($this->index);

    // Assert: The custom analyzer should be defined in the index as we
    // specified it in
    // \Drupal\elasticsearch_connector\Event\SynonymsSubscriber::onAlterSettings().
    $this->assertEqualsCanonicalizing(
      ['lowercase', 'asciifolding', 'synonyms'],
      $indexSettings['test_elasticsearch_index']['settings']['index']['analysis']['analyzer']['querytime_synonyms']['filter'],
    );
    $this->assertEquals('custom', $indexSettings['test_elasticsearch_index']['settings']['index']['analysis']['analyzer']['querytime_synonyms']['type']);
    $this->assertEquals('standard', $indexSettings['test_elasticsearch_index']['settings']['index']['analysis']['analyzer']['querytime_synonyms']['tokenizer']);
  }

  /**
   * Test the synonyms analysis filter.
   *
   * @see \Drupal\elasticsearch_connector\Event\SynonymsSubscriber::onAlterSettings()
   */
  public function testSynonymsFilter(): void {
    // SUT: Load the index settings from the backend.
    $indexSettings = $this->serverBackend->getRawIndexSettings($this->index);

    // Assert: The custom analysis filter should be defined in the index as we
    // specified it in
    // \Drupal\elasticsearch_connector\Event\SynonymsSubscriber::onAlterSettings().
    $this->assertEquals('synonym_graph', $indexSettings['test_elasticsearch_index']['settings']['index']['analysis']['filter']['synonyms']['type']);
    $this->assertEquals('true', $indexSettings['test_elasticsearch_index']['settings']['index']['analysis']['filter']['synonyms']['lenient']);
    $this->assertEqualsCanonicalizing([
      'test => check',
      'hello, hi',
    ], $indexSettings['test_elasticsearch_index']['settings']['index']['analysis']['filter']['synonyms']['synonyms']);
  }

}
