<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\ImageAndAudioToVideo\ImageAndAudioToVideoInput;
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
  id: 'image_and_audio_to_video_generator',
  title: new TranslatableMarkup('Image-and-Audio-to-Video Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI image and audio to video tool with prompts.'),
)]
final class ImageAndAudioToVideoGenerator extends AiApiExplorerPluginBase {

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
    return $this->providerManager->hasProvidersForOperationType('image_and_audio_to_video');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get the query string for provider_id, model_id.
    $request = $this->getRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('ata_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('ata_ai_model', $request->query->get('model_id'));
    }

    $form = $this->getFormTemplate($form, 'ai-video-response');

    $form['left']['file'] = [
      '#type' => 'file',
      // Only mp3 files are allowed in this case, since that covers most models.
      '#accept' => '.mp3',
      '#title' => $this->t('Upload your audio here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    $form['left']['image'] = [
      '#type' => 'file',
      '#accept' => '.jpg, .jpeg, .png',
      '#title' => $this->t('Upload your image here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'image_and_audio_to_video', 'ata', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['ata_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate an Video File'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-video-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'image_and_audio_to_video', 'ata');

      $audio_file = $this->generateFile();
      $image_file = $this->generateFile('image');

      if (!$audio_file || !$image_file) {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Files Provided'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please upload both an audio file (MP3) and an image file (JPG, JPEG, or PNG) to generate a video.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      $input = new ImageAndAudioToVideoInput($image_file, $audio_file);
      $video_normalized = $provider->ImageAndAudioToVideo($input, $form_state->getValue('image_and_audio_to_video_ai_model'), ['ai_api_explorer'])->getNormalized();

      if ($video_normalized) {
        // Save the binary data to a file.
        $destination = 'temporary://ai-explorers/';
        $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
        $random = (string) rand();
        $file_url = $this->fileSystem->saveData($video_normalized, $destination . '/' . md5($random) . '.mp4');
        $file_name = basename($file_url);
        $url = Url::fromRoute('system.temporary', [], ['query' => ['file' => 'ai-explorers/' . $file_name]]);
        $form['right']['response']['#context']['ai_response']['response'] = [
          '#type' => 'inline_template',
          '#template' => '{{ player|raw }}',
          '#context' => [
            'player' => '<video controls><source src="' . $url->toString() . '" type="video/mp4"></video>',
          ],
        ];
      }
      else {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Video Generated'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('The provider did not generate a video. Please check your input files and try again.'),
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
