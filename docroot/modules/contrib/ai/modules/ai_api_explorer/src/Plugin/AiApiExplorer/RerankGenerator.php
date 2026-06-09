<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\Rerank\ReRankInput;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the ai_api_explorer for reranking documents.
 */
#[AiApiExplorer(
  id: 'rerank_generator',
  title: new TranslatableMarkup('Rerank Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI rerank operation.'),
)]
final class RerankGenerator extends AiApiExplorerPluginBase {

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('rerank');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = $this->getFormTemplate($form, 'ai-rerank-response');

    $form['left']['query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Query'),
      '#description' => $this->t('The search query to rerank documents against.'),
      '#required' => TRUE,
      '#default_value' => 'What is the capital of France?',
    ];

    $form['left']['documents'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Documents'),
      '#description' => $this->t('Enter documents to rerank, one per line.'),
      '#required' => TRUE,
      '#default_value' => "Paris is the capital of France.\nLondon is the capital of the UK.\nBerlin is the capital of Germany.\nFrance has a beautiful capital city.",
      '#rows' => 10,
    ];

    $form['left']['top_n'] = [
      '#type' => 'number',
      '#title' => $this->t('Top N'),
      '#description' => $this->t('Number of top results to return. Leave empty or 0 to return all.'),
      '#default_value' => 3,
      '#min' => 0,
    ];

    // Load the provider configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'rerank', 'rerank', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['rerank_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['ai-submit-wrapper'],
        'style' => 'display: flex; align-items: center; gap: 5px;',
      ],
    ];

    $form['left']['submit_wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rerank Documents'),
      '#attributes' => [
        'data-response' => 'ai-rerank-response',
        'class' => ['ai-submit-button'],
      ],
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-rerank-response',
        'event' => 'click',
      ],
    ];

    $form['left']['submit_wrapper']['loading'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'id' => 'ai-loading-message-rerank',
        'class' => ['ai-loading'],
        'style' => 'display: none;',
      ],
      '#value' => $this->t('Processing...'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'rerank', 'rerank');
      $query = $form_state->getValue('query');
      $documents_raw = $form_state->getValue('documents');
      $top_n = (int) $form_state->getValue('top_n');

      // Split documents by newline.
      $documents = preg_split('/\r\n|\r|\n/', $documents_raw);
      $documents = array_filter($documents, fn($value) => !is_null($value) && $value !== '');
      $documents = array_values($documents);

      if (!empty($query) && !empty($documents)) {
        // Create input.
        $input = new ReRankInput($query, $documents, $top_n);

        $result = $provider->rerank($input, $form_state->getValue('rerank_ai_model'), ['ai_api_explorer']);
        $normalized = $result->getNormalized();

        // Format response.
        $output_html = '<ul>';
        foreach ($normalized as $item) {
          // Handle both array and object response formats from different
          // providers.
          $doc_text = '';
          $score = 0;
          $index = -1;

          if (is_array($item)) {
            $doc_text = $item['document']['text'] ?? ($item['text'] ?? '');
            // If document is just a string in the result.
            if (empty($doc_text) && isset($item['document']) && is_string($item['document'])) {
              $doc_text = $item['document'];
            }
            $score = $item['relevance_score'] ?? ($item['score'] ?? 0);
            $index = $item['index'] ?? -1;
          }
          elseif (is_object($item)) {
            $doc_text = $item->document->text ?? ($item->text ?? '');
            $score = $item->relevance_score ?? ($item->score ?? 0);
            $index = $item->index ?? -1;
          }

          $output_html .= '<li>';
          $output_html .= '<strong>' . $this->t('Score: @score', ['@score' => $score]) . '</strong> ';
          $output_html .= '(' . $this->t('Original Index: @index', ['@index' => $index]) . ')<br>';
          $output_html .= '<em>' . Html::escape($doc_text) . '</em>';
          $output_html .= '</li>';
        }
        $output_html .= '</ul>';

        // Show raw JSON for debugging.
        $output_html .= '<details><summary>' . $this->t('Raw Response') . '</summary><pre>' . Html::escape(Json::encode($normalized)) . '</pre></details>';

        $form['right']['response']['#context']['ai_response']['response'] = [
          '#type' => 'markup',
          '#markup' => $output_html,
        ];
        $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($provider, $form_state, $query, $documents, $top_n);
      }
      else {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('Missing Input'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please provide both a query and documents.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
      }
    }
    catch (\TypeError $e) {
      $form['right']['response']['#context']['ai_response'] = [
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Configuration Error'),
        ],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('The AI provider could not be used. Please make sure a model is selected and the provider is properly configured.'),
          '#attributes' => [
            'class' => ['ai-text-response', 'ai-error-message'],
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      $form['right']['response']['#context']['ai_response'] = [
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Error'),
        ],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->explorerHelper->renderException($e),
          '#attributes' => [
            'class' => ['ai-text-response', 'ai-error-message'],
          ],
        ],
      ];
    }

    $form_state->setRebuild();
    return $form['right'];
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $query
   *   The query.
   * @param array $documents
   *   The documents.
   * @param int $top_n
   *   Top N results.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $query, array $documents, int $top_n): array {
    $code = $this->getCodeExampleTemplate();

    $show_config = count($provider->getConfiguration());
    if ($show_config) {
      $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    }

    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('rerank_ai_provider') . "');<br>";
    if ($show_config) {
      $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    }
    $code['code']['#value'] .= "// Prepare input.<br>";
    $code['code']['#value'] .= "\$documents = " . Json::encode($documents) . ";<br>";
    $code['code']['#value'] .= "\$input = new \\Drupal\\ai\\OperationType\\Rerank\\ReRankInput('" . Html::escape($query) . "', \$documents, " . $top_n . ");<br><br>";

    $code['code']['#value'] .= "\$response = \$ai_provider->rerank(\$input, '" . $form_state->getValue('rerank_ai_model') . "', ['your_module_name']);<br><br>";
    $code['code']['#value'] .= "// Get normalized results as an array of ranked items.<br>";
    $code['code']['#value'] .= "\$normalized = \$response->getNormalized();<br>";

    return $code;
  }

  /**
   * Ajax callback accounting for the different form structure.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The correct section of the form.
   *
   * @see \Drupal\ai\Service\AiProviderFormHelper::loadModelsAjaxCallback
   */
  public static function loadModelsAjaxCallback(array &$form, FormStateInterface $form_state): mixed {
    $prefix = $form_state->getTriggeringElement()['#ajax']['data-prefix'] ?? '';
    $form_state->setRebuild();
    return $form['left'][$prefix . 'ajax_prefix'];
  }

}
