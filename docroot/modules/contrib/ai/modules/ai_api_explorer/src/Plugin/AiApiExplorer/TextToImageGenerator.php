<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\AiProviderPluginManager;
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
  id: 'text_to_image_generator',
  title: new TranslatableMarkup('Text-To-Image Generation Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI image generator with prompts.'),
)]
final class TextToImageGenerator extends AiApiExplorerPluginBase {

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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The File System.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $requestStack, AiProviderFormHelper $aiProviderHelper, ExplorerHelper $explorerHelper, AiProviderPluginManager $providerManager, protected ModuleHandlerInterface $moduleHandler, protected EntityTypeManagerInterface $entityTypeManager, protected FileSystemInterface $fileSystem) {
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
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('text_to_image');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get the query string for provider_id, model_id.
    $request = $this->getRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('image_generator_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('image_generator_ai_model', $request->query->get('model_id'));
    }

    $input = json_decode($request->query->get('input', '[]'));
    $form = $this->getFormTemplate($form, 'ai-image-response');

    $form['left']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => $input,
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'text_to_image', 'image_generator', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['image_generator_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    // If media module exists.
    if ($this->moduleHandler->moduleExists('media')) {
      $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
      $media_options = [
        '' => $this->t('None'),
      ];
      foreach ($media_types as $media_type) {
        $media_options[$media_type->id()] = $media_type->label();
      }
      $form['left']['save_as_media'] = [
        '#type' => 'select',
        '#title' => $this->t('Save as media'),
        '#options' => $media_options,
        '#description' => $this->t('If you want to save the image as media, select the media type.'),
      ];
    }

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate an Image'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'ai-image-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'text_to_image', 'image_generator');
      $prompt = $form_state->getValue('prompt');

      if (empty($prompt)) {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Prompt Provided'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please enter a prompt to generate an image.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      $images = $provider->textToImage($prompt, $form_state->getValue('image_generator_ai_model'), ['ai_api_explorer'])->getNormalized();

      if (!empty($images) && is_array($images)) {
        $key = 0;

        /** @var \Drupal\ai\OperationType\GenericType\ImageFile $image */
        foreach ($images as $image) {

          // Save the binary data to a file to prevent browsers caching multiple
          // generated images.
          $destination = 'temporary://ai-explorers/';
          $this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY);
          $random = (string) rand();
          $file_url = $this->fileSystem->saveData($image->getBinary(), $destination . '/' . md5($random) . '.png');
          $file_name = basename($file_url);
          $url = Url::fromRoute('system.temporary', [], ['query' => ['file' => 'ai-explorers/' . $file_name]]);
          $form['right']['response']['#context']['ai_response']['image_' . $key] = [
            '#theme' => 'image',
            '#uri' => $url->toString(),
          ];

          $key++;

          if ($form_state->getValue('save_as_media')) {
            if ($media = $image->getAsMediaEntity($form_state->getValue('save_as_media'), '', 'image.png')) {
              $media->save();
            }
          }
        }

        if ($key === 0) {
          $form['right']['response']['#context']['ai_response'] = [
            'heading' => [
              '#type' => 'html_tag',
              '#tag' => 'h3',
              '#value' => $this->t('No Images Generated'),
            ],
            'message' => [
              '#type' => 'html_tag',
              '#tag' => 'div',
              '#value' => $this->t('The provider did not generate any valid images. Please check your prompt and try again.'),
              '#attributes' => [
                'class' => ['ai-text-response', 'ai-error-message'],
              ],
            ],
          ];
        }
        else {
          $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($provider, $form_state, $prompt);
        }
      }
      else {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Images Generated'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('The provider did not generate any valid images. Please check your prompt and try again.'),
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
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $prompt): array {
    $code = $this->getCodeExampleTemplate();
    $code['code']['#value'] .= '$prompt = "' . $prompt . '";<br>';
    $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('image_generator_ai_provider') . '\');<br>';
    $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code['code']['#value'] .= "// Normalize the input.<br>";
    $code['code']['#value'] .= "\$input = new \Drupal\ai\OperationType\TextToImage\TextToImageInput(\$prompt);<br>";
    $code['code']['#value'] .= "// This gets an array of \Drupal\ai\OperationType\GenericType\ImageFile.<br>";
    $code['code']['#value'] .= "\$normalized = \$ai_provider->textToImage(\$input, '" . $form_state->getValue('image_generator_ai_model') . '\', ["tag_1", "tag_2"])->getNormalized();<br><br>';
    $code['code']['#value'] .= "// Examples Possibility #1 - get binary from the first image.<br>";
    $code['code']['#value'] .= '$binaries = $normalized[0]->getAsBinary();<br>';
    $code['code']['#value'] .= "// Examples Possibility #2 - get as base 64 encoded string from the first image.<br>";
    $code['code']['#value'] .= '$base64 = $normalized[0]->getAsBase64EncodedString();<br>';
    $code['code']['#value'] .= "// Examples Possibility #3 - get as generated media from the first image.<br>";
    $code['code']['#value'] .= '$media = $normalized[0]->getAsMediaEntity("image", "", "image.png");<br>';
    $code['code']['#value'] .= "// Examples Possibility #4 - get as image file entity from the first image.<br>";
    $code['code']['#value'] .= '$file = $normalized[0]->getAsFileEntity("public://", "image.png");<br><br>';
    $code['code']['#value'] .= "// Another possibility is to get the raw response from the provider.<br>";
    $code['code']['#value'] .= '$raw = $response->getRaw();<br>';

    return $code;
  }

}
