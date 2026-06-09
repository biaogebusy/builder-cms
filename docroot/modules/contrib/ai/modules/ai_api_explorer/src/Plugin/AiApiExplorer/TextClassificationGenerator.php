<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\TextClassification\TextClassificationInput;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: 'text_classification_generator',
  title: new TranslatableMarkup('Text Classification Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI text classification with prompts and labels.'),
)]
final class TextClassificationGenerator extends AiApiExplorerPluginBase {

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('text_classification');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = $this->getFormTemplate($form, 'text-classify-response');

    $form['left']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter your text here. When submitted, your provider will generate a classification. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your text, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => '',
      '#required' => TRUE,
    ];

    $form['left']['labels'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Labels'),
      '#description' => $this->t('New line separated list of labels to filter the classification if the model takes it.'),
      '#attributes' => [
        'placeholder' => "positive\nnegative\nneutral\n",
      ],
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'text_classification', 'text_class', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['text_class_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Classify Text'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'text-classify-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'text_classification', 'text_class');

      $prompt = $form_state->getValue('prompt');

      if (empty($prompt)) {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Text Provided'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please enter text to classify.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      // Get the labels.
      $labels = explode("\n", $form_state->getValue('labels') ?? '');
      $labels = array_map('trim', $labels);
      $labels = array_filter($labels, function ($label) {
        return !empty(trim($label));
      });

      $input = new TextClassificationInput($prompt, $labels);
      $classification = $provider->textClassification($input, $form_state->getValue('text_class_ai_model'), ['text_classification_explorer'])->getNormalized();

      if ($classification) {
        $form['right']['response']['#context']['ai_response']['table'] = [
          '#type' => 'table',
          '#header' => [
            'label' => $this->t('Label'),
            'score' => $this->t('Score'),
          ],
          '#rows' => [],
          '#empty' => $this->t('There was an issue retrieving classifications.'),
        ];
        foreach ($classification as $row) {
          $form['right']['response']['#context']['ai_response']['table']['#rows'][] = [
            $this->t('<strong>:label</strong>', [
              ':label' => $row->getLabel(),
            ]),
            $this->t('<em>:score</em>', [
              ':score' => $row->getConfidenceScore(),
            ]),
          ];
        }

        $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($provider, $form_state, $prompt, $labels);
      }
      else {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Classification Generated'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('The provider did not generate a classification. Please check your input and try again.'),
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
   * @param string $prompt
   *   The prompt.
   * @param string[] $labels
   *   The labels.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $prompt, array $labels): array {
    $code = $this->getCodeExampleTemplate();
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    }
    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('text_class_ai_provider') . "');<br>";
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    }
    $code['code']['#value'] .= "// Normalize the input.<br>";
    $code['code']['#value'] .= "\$text = '" . $prompt . "';<br>";

    if (count($labels)) {
      $code['code']['#value'] .= "\$labels = [<br>";
      foreach ($labels as $label) {
        $code['code']['#value'] .= '&nbsp;&nbsp;"' . $label . '",<br>';
      }
      $code['code']['#value'] .= "];<br>";
      $code['code']['#value'] .= "\$input = new \\Drupal\\ai\\OperationType\\TextClassification\\TextClassificationInput(\$text, \$labels);<br><br>";
    }
    else {
      $code['code']['#value'] .= "\$input = new \\Drupal\\ai\\OperationType\\TextClassification\\TextClassificationInput(\$text);<br><br>";
    }
    $code['code']['#value'] .= "// Run the classification.<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->textClassification(\$input, '" . $form_state->getValue('text_class_ai_model') . "', ['your_module_name']);<br><br>";
    $code['code']['#value'] .= "// Output is an array of TextClassificationItem objects.<br>";
    $code['code']['#value'] .= "\$classifications = \$response->getNormalized();<br>";

    return $code;
  }

}
