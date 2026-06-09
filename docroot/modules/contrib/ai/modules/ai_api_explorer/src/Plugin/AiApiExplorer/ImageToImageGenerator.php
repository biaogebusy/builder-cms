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
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;
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
  id: 'image_to_image_generator',
  title: new TranslatableMarkup('Image-To-Image Explorer'),
  description: new TranslatableMarkup('Contains a form where you can play around with image to image stuff.'),
)]
final class ImageToImageGenerator extends AiApiExplorerPluginBase {

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
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $requestStack,
    AiProviderFormHelper $aiProviderHelper,
    ExplorerHelper $explorerHelper,
    AiProviderPluginManager $providerManager,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected FileSystemInterface $fileSystem,
  ) {
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
    return $this->providerManager->hasProvidersForOperationType('image_to_image');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get the query string for provider_id, model_id.
    $request = $this->getRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('image_to_image_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('image_to_image_ai_model', $request->query->get('model_id'));
    }

    $form = $this->getFormTemplate($form, 'image-to-image-response');

    $form['left']['image'] = [
      '#type' => 'file',
      '#accept' => '.jpg, .jpeg, .png',
      '#title' => $this->t('Upload your image here. When submitted, your provider will generate a classification. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'image_to_image', 'image_to_image', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['image_to_image_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    // Special logic for Image to Image models.
    $provider = $form_state->getValue('image_to_image_ai_provider');
    $model = $form_state->getValue('image_to_image_ai_model');

    if ($provider && $model) {
      $instance = $this->providerManager->createInstance($provider);
      if ($instance->hasImageToImagePrompt($model)) {
        $form['left']['image_to_image_ajax_prefix']['prompt'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Prompt'),
          '#description' => $this->t('The prompt to use for the image to image generation.'),
          '#required' => $instance->requiresImageToImagePrompt($model),
          '#weight' => -20,
        ];
      }

      if ($instance->hasImageToImageMask($model)) {
        $form['left']['image_to_image_ajax_prefix']['mask'] = [
          '#type' => 'file',
          '#title' => $this->t('Mask Image'),
          '#description' => $this->t('The mask image to use for the image to image generation.'),
          '#accept' => '.png',
          '#required' => $instance->requiresImageToImageMask($model),
          '#weight' => -10,
        ];
      }
    }

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Image'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'image-to-image-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'image_to_image', 'image_to_image');

    if ($image_file = $this->generateFile('image')) {
      $input = new ImageToImageInput($image_file);

      if ($form_state->getValue('prompt')) {
        $input->setPrompt($form_state->getValue('prompt'));
      }

      // Set the mask if it exists.
      $files = $this->getRequest()->files->all();
      $mask = $files['files']['mask'] ?? NULL;
      if ($mask) {
        $mime_type = $mask->getMimeType();
        $raw_file = file_get_contents($mask->getPathname());
        $file_name = $mask->getClientOriginalName();
        $input->setMask(new ImageFile($raw_file, $mime_type, $file_name));
      }

      try {
        $images = $provider->imageToImage($input, $form_state->getValue('image_to_image_ai_model'), ['ai_api_explorer'])->getNormalized();
      }
      catch (\Exception $e) {
        $form['right']['response']['#context']['ai_response']['response'] = [
          '#type' => 'inline_template',
          '#template' => '{{ error|raw }}',
          '#context' => [
            'error' => $this->explorerHelper->renderException($e),
          ],
        ];

        // Early return if we've hit an error.
        return $form['right'];
      }

      if ($images) {
        foreach ($images as $key => $image) {
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
        }

        $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($provider, $form_state, $image_file->getFilename(), $input);
      }
    }

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
   * @param \Drupal\ai\OperationType\ImageToImage\ImageToImageInput $input
   *   The input for the image to image operation.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $filename, ImageToImageInput $input): array {
    // Generation code.
    $code = $this->getCodeExampleTemplate();
    $code['code']['#value'] .= '$binary = file_get_contents("' . $filename . '");<br>';
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    }
    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('image_to_image_ai_provider') . '\');<br>';
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    }
    $code['code']['#value'] .= "// Normalize the input.<br>";
    $code['code']['#value'] .= "\$image = new \Drupal\ai\OperationType\GenericType\ImageFile(\$binary, 'image/jpeg', '" . $filename . "');<br>";

    $code['code']['#value'] .= "\$input = new \Drupal\ai\OperationType\ImageToImage\ImageToImageInput(\$image);<br>";

    // If the input has a mask, we need to add it.
    if ($input->getMask()) {
      $code['code']['#value'] .= '$mask_binary = file_get_contents("' . $filename . '");<br>';
      if (count($provider->getConfiguration())) {
        $code['code']['#value'] .= $this->addProviderCodeExample($provider);
      }
      $code['code']['#value'] .= "\$mask = new \Drupal\ai\OperationType\GenericType\ImageFile(\$mask_binary, 'image/png', 'mask.png');<br>";
      $code['code']['#value'] .= "\$input->setMask(\$mask);<br>";
    }

    // If the input has a prompt, we need to add it.
    if ($input->getPrompt()) {
      $code['code']['#value'] .= "\$input->setPrompt('" . $form_state->getValue('prompt') . "');<br>";
    }

    $code['code']['#value'] .= "// Run the classification.<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->imageToImage(\$input, '" . $form_state->getValue('image_to_image_ai_model') . '\', ["your_module_name"]);<br><br>';
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
