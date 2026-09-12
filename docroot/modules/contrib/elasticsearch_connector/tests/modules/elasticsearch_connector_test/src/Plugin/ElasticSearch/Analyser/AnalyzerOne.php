<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector_test\Plugin\ElasticSearch\Analyser;

use Drupal\elasticsearch_connector\Analyser\AnalyserBase;

// cspell:ignore stopwords _english_

/**
 * A test analyzer defined through a plugin.
 *
 * Note that it is possible to alter an analyzer definition using the
 * \Drupal\elasticsearch_connector\Event\AlterSettingsEvent: see
 * \Drupal\elasticsearch_connector_test\EventSubscriber\AnalyzerAssignmentSubscriber::onAlterIndexSettings()
 * for an example.
 *
 * This is the "more complicated example" copied from the Elastic documentation.
 *
 * @see https://www.elastic.co/docs/manage-data/data-store/text-analysis/create-custom-analyzer#:~:text=Here%20is%20a%20more%20complicated%20example
 * @see \Drupal\elasticsearch_connector_test\EventSubscriber\AnalyzerAssignmentSubscriber
 *
 * @ElasticSearchAnalyser(
 *   id = "elasticsearch_connector_test_analyzer_one",
 *   label = @Translation("ElasticSearch Connector Test Analyzer"),
 * )
 */
class AnalyzerOne extends AnalyserBase {

  /**
   * {@inheritdoc}
   */
  #[\Override]
  public function getSettings(): array {
    return [
      "analysis" => [
        "analyzer" => [
          "elasticsearch_connector_test_analyzer_one" => [
            "char_filter" => [
              "emoticons",
            ],
            "tokenizer" => "punctuation",
            "filter" => [
              "lowercase",
              "english_stop",
            ],
          ],
        ],
        "tokenizer" => [
          "punctuation" => [
            "type" => "pattern",
            "pattern" => "[ .,!?]",
          ],
        ],
        "char_filter" => [
          "emoticons" => [
            "type" => "mapping",
            "mappings" => [
              ":) => _happy_",
              ":( => _sad_",
            ],
          ],
        ],
        "filter" => [
          "english_stop" => [
            "type" => "stop",
            "stopwords" => "_english_",
          ],
        ],
      ],
    ];
  }

}
