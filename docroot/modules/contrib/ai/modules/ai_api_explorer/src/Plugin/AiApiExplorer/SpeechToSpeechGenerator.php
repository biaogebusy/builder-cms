<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInput;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;
use Drupal\ai_api_explorer\ExplorerHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: 'speech_to_speech_generator',
  title: new TranslatableMarkup('Speech-To-Speech Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI speech to speech tool with prompts.'),
)]
final class SpeechToSpeechGenerator extends AiApiExplorerPluginBase {

  /**
   * Constructs the base plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\ai\Service\AiProviderFormHelper $aiProviderHelper
   *   The AI Provider Helper.
   * @param \Drupal\ai_api_explorer\ExplorerHelper $explorerHelper
   *   The Explorer helper.
   * @param \Drupal\ai\AiProviderPluginManager $providerManager
   *   The Provider Manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The File Url Generator.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The File System.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $requestStack, AiProviderFormHelper $aiProviderHelper, ExplorerHelper $explorerHelper, AiProviderPluginManager $providerManager, protected FileUrlGeneratorInterface $fileUrlGenerator, protected FileSystemInterface $fileSystem) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $requestStack, $aiProviderHelper, $explorerHelper, $providerManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('ai.form_helper'),
      $container->get('ai_api_explorer.helper'),
      $container->get('ai.provider'),
      $container->get('file_url_generator'),
      $container->get('file_system'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('speech_to_speech');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get the query string for provider_id, model_id.
    $request = $this->getRequest();

    if ($request->query->get('provider_id')) {
      $form_state->setValue('sts_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('sts_ai_model', $request->query->get('model_id'));
    }

    $form = $this->getFormTemplate($form, 'ai-audio-response');

    $form['left']['file'] = [
      '#type' => 'file',
      // Only mp3 files are allowed in this case, since that covers most models.
      '#accept' => '.mp3',
      '#title' => $this->t('Upload your file here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'speech_to_speech', 'sts', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['sts_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    // Add submit button.
    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate an Audio Response'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-audio-response',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'speech_to_speech', 'speech_to_speech');
      $audio_file = $this->generateFile();

      if (!$audio_file) {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Audio File Provided'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please upload an audio file (MP3) to process.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      $input = new SpeechToSpeechInput($audio_file);
      $audio_normalized = $provider->speechToSpeech($input, $form_state->getValue('speech_to_speech_ai_model'), ['ai_api_explorer'])->getNormalized();

      if (!empty($audio_normalized) && is_array($audio_normalized) && isset($audio_normalized[0]) && method_exists($audio_normalized[0], 'getBinary')) {
        // Save the binary data to a file.
        $destination = 'temporary://ai-explorers/';
        $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
        $random = (string) rand();
        $binary_data = $audio_normalized[0]->getBinary();
        $file_url = $this->fileSystem->saveData($binary_data, $destination . '/' . md5($random) . '.mp3');
        $file_name = basename($file_url);
        $url = Url::fromRoute('system.temporary', [], ['query' => ['file' => 'ai-explorers/' . $file_name]]);
        $form['right']['response']['#context']['ai_response']['response'] = [
          '#type' => 'inline_template',
          '#template' => '{{ player|raw }}',
          '#context' => [
            'player' => '<audio controls><source src="' . $url->toString() . '" type="audio/mpeg"></audio>',
          ],
        ];

        $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($provider, $form_state, $audio_file->getFilename());
      }
      else {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Audio Generated'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('The provider did not generate an audio response. Please check your input file and try again.'),
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
   * @param string $filename
   *   The filename.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $filename): array {
    // Generation code.
    $code = $this->getCodeExampleTemplate();
    $code['code']['#value'] .= '$binary = file_get_contents("' . $filename . '");<br>';
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    }

    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('sts_ai_provider') . '\');<br>';
    $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code['code']['#value'] .= "// Normalize the input.<br>";
    $code['code']['#value'] .= "\$audio_file = new \Drupal\ai\OperationType\GenericType\AudioFile(\$binary, 'audio/mp3', '" . $filename . "');<br>";
    $code['code']['#value'] .= "\$input = new \Drupal\ai\OperationType\SpeechToSpeech\SpeechToSpeechInput(\$audio_file);<br>";
    $code['code']['#value'] .= "// \$response will be a AudioFile with the text.<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->speechToSpeech(\$input, '" . $form_state->getValue('sts_ai_model') . '\', ["your_module_name"]);';
    $code['code']['#value'] .= "// This gets an array of \Drupal\ai\OperationType\GenericType\AudioFile.<br>";
    $code['code']['#value'] .= "\$normalized = \$response->getNormalized();<br><br>";
    $code['code']['#value'] .= "// Examples Possibility #1 - get binary from the first audio.<br>";
    $code['code']['#value'] .= '$binaries = $normalized[0]->getAsBinary();<br>';
    $code['code']['#value'] .= "// Examples Possibility #2 - get as base 64 encoded string from the first audio.<br>";
    $code['code']['#value'] .= '$base64 = $normalized[0]->getAsBase64EncodedString();<br>';
    $code['code']['#value'] .= "// Examples Possibility #3 - get as generated media from the first audio.<br>";
    $code['code']['#value'] .= '$media = $normalized[0]->getAsMediaEntity("audio", "", "audio.mp3");<br>';
    $code['code']['#value'] .= "// Examples Possibility #4 - get as file entity from the first audio.<br>";
    $code['code']['#value'] .= '$file = $normalized[0]->getAsFileEntity("public://", "audio.mp3");<br><br>';
    $code['code']['#value'] .= "// Another possibility is to get the raw response from the provider.<br>";
    $code['code']['#value'] .= '$raw = $response->getRaw();<br>';

    return $code;
  }

}
