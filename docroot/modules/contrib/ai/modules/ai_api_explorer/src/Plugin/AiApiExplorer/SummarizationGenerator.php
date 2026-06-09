<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\Summarization\SummarizationInput;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: 'summarize_generator',
  title: new TranslatableMarkup('Summarize Text Explorer'),
  description: new TranslatableMarkup('Contains a form where you can test text summarization.'),
)]
final class SummarizationGenerator extends AiApiExplorerPluginBase {

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('summarize');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get the query string for provider_id, model_id.
    $request = $this->getRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('sum_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('sum_ai_model', $request->query->get('model_id'));
    }

    $input = json_decode($request->query->get('input', '[]'));
    $form = $this->getFormTemplate($form, 'ai-text-response');

    $form['left']['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter the text to summarize.'),
      '#description' => $this->t('Please note that each query counts against your API usage if your provider is a paid provider. Based on the complexity of your text, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => $input,
      '#required' => TRUE,
    ];
    $form['left']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Summarization prompt (optional)'),
      '#description' => $this->t('Optional instructions to guide the summarization. For example: "Summarize in 3 bullet points" or "Create a one-sentence summary". Not all models support this.'),
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'summarize', 'sum', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['sum_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Summarize the text'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-text-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'summarize', 'sum');
      $text = $form_state->getValue('text');
      $prompt = $form_state->getValue('prompt') ?: NULL;
      $model = $form_state->getValue('sum_ai_model');

      if (empty($text)) {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Text Provided'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please enter text to summarize.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      $input = new SummarizationInput($text, $prompt);
      $summary = $provider->summarize($input, $model, ['ai_api_explorer'])->getNormalized();

      if (!empty($summary) && is_string($summary)) {
        $form['right']['response']['#context']['ai_response']['response'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'),
          '#attributes' => [
            'class' => ['ai-text-response'],
          ],
        ];
      }
      else {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Summary Generated'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('The provider did not generate a valid summary. Please check your input and try again.'),
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

}
