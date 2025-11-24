<?php

namespace Drupal\elasticsearch_connector\Plugin\search_api\processor;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;

/**
 * Adds a highlighted excerpt to results using ElasticSearch's highlighter.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/highlighting.html#highlighting-settings
 * @see \search_excerpt()
 *
 * @SearchApiProcessor(
 *   id = "elasticsearch_highlight",
 *   label = @Translation("Elasticsearch Highlighter"),
 *   description = @Translation("Uses ElasticSearch's highlighter to generate an excerpt."),
 *   stages = {
 *     "preprocess_query" = 0,
 *     "postprocess_query" = 0,
 *   },
 * )
 */
class ElasticsearchHighlighter extends ProcessorPluginBase implements PluginFormInterface {
  use HighlightQueryBuilderTrait;
  use LoggerTrait;
  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    $form['fields'] = [
      '#type' => 'select',
      '#title' => $this->t('Fields to highlight'),
      '#description' => $this->t('The fields to search for highlights in, and to display highlights for.'),
      '#default_value' => $config['fields'],
      '#multiple' => TRUE,
      '#options' => $this->getFieldOptions($this->index),
      '#states' => [
        'required' => [
          ':input[name="status[elasticsearch_highlight]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['help_terms'] = [
      '#type' => 'markup',
      '#markup' => $this->t("The ElasticSearch server's highlighter returns one or more snippets of text for each field in each matching search result (Drupal core calls these snippets, and ElasticSearch documentation calls them fragments). The ElasticSearch Connector module combines the snippets from all fields into a Search API excerpt, which can be displayed to the end-user."),
    ];

    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Highlighter type'),
      '#default_value' => $config['type'],
      '#options' => [
        'unified' => $this->t('Unified'),
        'plain' => $this->t('Plain'),
      ],
      '#states' => [
        'required' => [
          ':input[name="status[elasticsearch_highlight]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['type']['unified']['#description'] = $this->t('Better when highlighting fields that contain HTML, or a mixture of plain-text fields and HTML fields.');
    $form['type']['plain']['#description'] = $this->t('Faster when highlighting fields that do not contain HTML.');

    $form['boundary_scanner'] = [
      '#type' => 'radios',
      '#title' => $this->t('Boundary scanner'),
      '#description' => $this->t('Only valid for the %unified_type highlighter type.', [
        '%unified_type' => $this->t('Unified'),
      ]),
      '#default_value' => $config['boundary_scanner'],
      '#options' => [
        'sentence' => $this->t('Sentence'),
        'word' => $this->t('Word'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="processors[elasticsearch_highlight][settings][type]"]' => [
            ['value' => 'unified'],
          ],
        ],
      ],
    ];
    $form['boundary_scanner']['sentence']['#description'] = $this->t('Snippets should break on sentence- or phrase-boundaries if possible.');
    $form['boundary_scanner']['word']['#description'] = $this->t('Snippets should break on word-boundaries if possible.');

    $form['boundary_scanner_locale'] = [
      '#type' => 'select',
      '#title' => $this->t('Boundary scanner locale'),
      '#description' => $this->t('In order for the boundary scanner to determine sentence boundaries, it needs to know which human language that the data is stored in. ElasticSearch does not currently support getting this from another field.'),
      '#default_value' => $config['boundary_scanner_locale'],
      '#options' => $this->getBoundaryScannerLocaleOptions(),
      '#states' => [
        'visible' => [
          ':input[name="processors[elasticsearch_highlight][settings][type]"]' => [
            ['value' => 'unified'],
          ],
          ':input[name="processors[elasticsearch_highlight][settings][boundary_scanner]"]' => [
            ['value' => 'sentence'],
          ],
        ],
      ],
    ];

    $form['fragmenter'] = [
      '#type' => 'radios',
      '#title' => $this->t('Fragmenter'),
      '#default_value' => $config['fragmenter'],
      '#options' => [
        'simple' => $this->t('Simple'),
        'span' => $this->t('Span'),
      ],
      '#description' => $this->t('Only valid for the %plain_type highlighter type.', [
        '%plain_type' => $this->t('Plain'),
      ]),
      '#states' => [
        'visible' => [
          ':input[name="processors[elasticsearch_highlight][settings][type]"]' => [
            ['value' => 'plain'],
          ],
        ],
      ],
    ];
    $form['fragmenter']['simple']['#description'] = $this->t('Puts each match into its own snippet, even if they are nearby/adjacent. If matching words are nearby in the text, the excerpt may repeat itself, or may contain unnecessary Snippet-joiners.');
    $form['fragmenter']['span']['#description'] = $this->t('Puts nearby/adjacent matches into a single snippet with multiple highlights in that snippet.');

    $form['pre_tag'] = [
      '#type' => 'textfield',
      '#default_value' => $config['pre_tag'],
      '#title' => $this->t('Highlight opening tag'),
      '#description' => $this->t("The HTML tag to surround the highlighted text with. ElasticSearch uses @emphasis_html_tag by default, but Drupal's core Search and Search API's default highlighter use @strong_html_tag by default. Some sites use @mark_html_tag. Adding one or more HTML classes can help with custom theming. Multiple tags are not supported.", [
        '@emphasis_html_tag' => '<em>',
        '@strong_html_tag' => '<strong>',
        '@mark_html_tag' => '<mark>',
      ]),
      '#states' => [
        'required' => [
          ':input[name="status[elasticsearch_highlight]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['encoder'] = [
      '#type' => 'radios',
      '#title' => $this->t('Snippet encoder'),
      '#default_value' => $config['encoder'],
      '#options' => [
        'default' => $this->t('No encoding'),
        'html' => $this->t('HTML'),
      ],
      '#description' => $this->t("ElasticSearch doesn't natively provide a way to remove HTML tags, only escape them. The ElasticSearch Connector module's post-processor strips out any HTML tags that are not the highlighting tag to avoid invalid HTML in snippets, so %default_option is safe to use and usually produces the results you would expect.", [
        '%default_option' => $this->t('No encoding'),
      ]),
      '#states' => [
        'required' => [
          ':input[name="status[elasticsearch_highlight]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['encoder']['default']['#description'] = $this->t('Simply insert the highlight tags without making any changes to the snippet.');
    $form['encoder']['html']['#description'] = $this->t('Escape HTML in the snippet (i.e.: making the HTML tags in the field visible), before inserting the highlight tags.');

    $form['number_of_fragments'] = [
      '#type' => 'number',
      '#default_value' => $config['number_of_fragments'],
      '#title' => $this->t('Maximum number of snippets per field'),
      '#description' => $this->t("The maximum number of fragments to return for each field. If set to 0, no fragments are returned. ElasticSearch's default is 5."),
      '#min' => 0,
    ];

    $form['fragment_size'] = [
      '#type' => 'number',
      '#default_value' => $config['fragment_size'],
      '#title' => $this->t('Snippet size'),
      '#field_suffix' => $this->t('characters'),
      '#description' => $this->t("The approximate number of characters that should be in a snippet. When the Boundary scanner is set to %boundary_scanner_sentence, this value is treated as a maximum, and you can set it to 0 to never split a sentence. Drupal core's default is 60 characters; ElasticSearch's default is 100 characters.", [
        '%boundary_scanner_sentence' => $this->t('Sentence'),
      ]),
      '#min' => 0,
    ];

    $form['no_match_size'] = [
      '#type' => 'number',
      '#default_value' => $config['no_match_size'],
      '#title' => $this->t('Snippet size when there is no match'),
      '#description' => $this->t('If ElasticSearch finds a match in a field that is not selected for highlighting, it can return a snippet from the beginning of the field(s) that are selected for highlighting instead, so that there is an excerpt to display. It defaults to 0, meaning "do not return a snippet from a field that does not match". If you set it to a number greater than 0, this might produce unexpected results if you have also selected more than one field for highlighting!'),
      '#field_suffix' => $this->t('characters'),
      '#min' => 0,
    ];

    $form['order'] = [
      '#type' => 'radios',
      '#title' => $this->t('Snippet order'),
      '#default_value' => $config['order'],
      '#options' => [
        'none' => $this->t('Order snippets by the order they appear in the field'),
        'score' => $this->t('Order snippets so the most relevant snippets are first'),
      ],
      '#states' => [
        'required' => [
          ':input[name="status[elasticsearch_highlight]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['require_field_match'] = [
      '#type' => 'radios',
      '#title' => $this->t('Only show snippets from fields that match the query'),
      '#default_value' => $config['require_field_match'],
      '#options' => [
        0 => $this->t('Show snippets from all fields, even if they do not match the query'),
        1 => $this->t('Only show snippets from fields that match the query'),
      ],
    ];

    $form['snippet_joiner'] = [
      '#type' => 'textfield',
      '#default_value' => $config['snippet_joiner'],
      '#title' => $this->t('Snippet-joiner'),
      '#description' => $this->t("When joining snippets together into an excerpt, use this string between two snippets. Drupal's core Search and Search API's default highlighter use an ellipsis (…)."),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'boundary_scanner' => 'sentence',
      'boundary_scanner_locale' => LanguageInterface::LANGCODE_SYSTEM,
      'encoder' => 'default',
      'fields' => [],
      'fragment_size' => 60,
      'fragmenter' => 'span',
      'no_match_size' => 0,
      'number_of_fragments' => 5,
      'order' => 'none',
      'pre_tag' => '<em class="placeholder">',
      'require_field_match' => 1,
      'snippet_joiner' => ' … ',
      'type' => 'unified',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {
    // As per the processing levels defined in QueryInterface, we should only
    // process highlighting on searches with normal/full processing.
    if ($results->getQuery()->getProcessingLevel() === QueryInterface::PROCESSING_FULL) {
      $highlightingFields = $this->getConfiguration()['fields'];

      // Loop through each result item. If we have highlighting data about it,
      // then proceed with generating an excerpt for that result item.
      foreach ($results->getResultItems() as $resultItem) {
        if ($resultItem->hasExtraData('highlight')) {
          $highlightData = $resultItem->getExtraData('highlight');

          // Collect all the snippets into an array for combining at the end.
          $excerptArray = [];

          // Loop through each field. Only use the snippets from that field if
          // we were supposed to highlight from it.
          foreach ($highlightData as $fieldName => $fieldHighlights) {
            if (\in_array($fieldName, $highlightingFields)) {
              foreach ($fieldHighlights as $highlight) {
                $excerptArray[] = $this->createExcerpt($highlight);
              }
            }
          }

          // Combine the snippets into a single excerpt.
          $resultItem->setExcerpt(implode($this->getConfiguration()['snippet_joiner'], $excerptArray));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    parent::preprocessSearchQuery($query);

    // As per the processing levels defined in QueryInterface, we should only
    // process highlighting on searches with normal/full processing.
    if ($query->getProcessingLevel() === QueryInterface::PROCESSING_FULL) {
      $query->setOption('highlight', $this->buildHighlightQueryFragment());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function requiresReindexing(?array $old_settings = NULL, ?array $new_settings = NULL) {
    return FALSE;
  }

  /**
   * Create an excerpt from ElasticSearch output.
   *
   * Note that ElasticSearch generates the excerpt, and inserts an HTML tag
   * highlighting the search term match(es) for us.
   *
   * @param string $input
   *   The raw highlighted string from ElasticSearch.
   *
   * @return string
   *   An escaped excerpt for Search API.
   *
   * @see search_excerpt()
   * @see \Drupal\search_api\Plugin\search_api\processor\Highlight::createExcerpt()
   */
  protected function createExcerpt(string $input): string {
    $preTagName = $this->convertHtmlTagToTagName($this->getConfiguration()['pre_tag']);
    return Xss::filter($input, [$preTagName]);
  }

  /**
   * Get an options list of configured Search API Index fields.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to get a list of configured fields from.
   *
   * @return array
   *   An associative array of Search API Index field options, suitable for use
   *   in a Form API select/radios #options array; where the keys are field
   *   machine names, and the values are field labels.
   */
  protected function getFieldOptions(IndexInterface $index): array {
    $answer = [];

    $indexFields = $index->getFields(TRUE);

    foreach ($indexFields as $fieldId => $field) {
      $answer[$fieldId] = $field->getLabel();
    }

    return $answer;
  }

}
