<?php

namespace Drupal\ai_search\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\ResultSetInterface;

/**
 * Set a minimum score threshold for results at the Search API level.
 *
 * @SearchApiProcessor(
 *   id = "ai_search_score_threshold",
 *   label = @Translation("AI Search Score Threshold"),
 *   description = @Translation("The minimum score for a result to be returned."),
 *   stages = {
 *     "postprocess_query" = 0,
 *   }
 * )
 */
class ScoreThreshold extends ProcessorPluginBase implements PluginFormInterface {
  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    if ($index->getServerInstance()->getBackendId() == 'search_api_ai_search') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'minimum_relevance_score' => 0.2,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState) {
    $form['minimum_relevance_score'] = [
      '#type' => 'number',
      '#step' => 0.01,
      '#min' => 0,
      '#max' => 1,
      '#required' => TRUE,
      '#title' => $this->t('Minimum relevance score'),
      '#description' => $this->t('Only return results that have a score higher than this. The score should be between 0 and 1. 0 will return all results. 1 is almost impossible to achieve and will likely never return results. Between 0.2 and 0.5 are most likely to be useful.'),
      '#default_value' => $this->configuration['minimum_relevance_score'] ?? $this->defaultConfiguration()['minimum_relevance_score'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsExactPhraseSearch() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {
    if ($results->getResultCount()) {
      $result_items = [];
      foreach ($results->getResultItems() as $item) {
        if ($item->getScore() >= $this->configuration['minimum_relevance_score']) {
          $result_items[$item->getId()] = $item;
        }
      }

      if (count($result_items) < $results->getResultCount()) {
        $results->setResultItems($result_items);
        $results->setResultCount(count($result_items));
      }
    }
  }

}
