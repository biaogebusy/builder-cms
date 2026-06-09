<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: 'translation_generator',
  title: new TranslatableMarkup('Translate Text Explorer'),
  description: new TranslatableMarkup('Contains a form where you can test text translations.'),
)]
final class TranslationGenerator extends AiApiExplorerPluginBase {

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('translate_text');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get the query string for provider_id, model_id.
    $request = $this->getRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('tt_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('tt_ai_model', $request->query->get('model_id'));
    }

    $input = json_decode($request->query->get('input', '[]'));
    $form = $this->getFormTemplate($form, 'ai-text-response');

    $form['left']['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response.'),
      '#description' => $this->t('Please note that each query counts against your API usage if your provider is a paid provider. Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => $input,
      '#required' => TRUE,
    ];
    $form['left']['source_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source language'),
      '#description' => $this->t('The language code of the text you are translating.'),
    ];
    $form['left']['target_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target language'),
      '#description' => $this->t('The language code you want to translate the text to.'),
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'translate_text', 'tt', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['tt_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Translate the text'),
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
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'translate_text', 'translate_text');
      $text = $form_state->getValue('text');
      $sourceLanguage = $form_state->getValue('source_language') ?? NULL;
      $targetLang = $form_state->getValue('target_language');
      $model = $form_state->getValue('tt_ai_model');

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
            '#value' => $this->t('Please enter text to translate.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      if (empty($targetLang)) {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Target Language Provided'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please specify a target language for translation.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      $input = new TranslateTextInput($text, $sourceLanguage, $targetLang);
      $translation = $provider->translateText($input, $model, ['ai_api_explorer'])->getNormalized();

      if (!empty($translation) && is_string($translation)) {
        $form['right']['response']['#context']['ai_response']['response'] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => htmlspecialchars($translation, ENT_QUOTES, 'UTF-8'),
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
            '#value' => $this->t('No Translation Generated'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('The provider did not generate a valid translation. Please check your input and try again.'),
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
