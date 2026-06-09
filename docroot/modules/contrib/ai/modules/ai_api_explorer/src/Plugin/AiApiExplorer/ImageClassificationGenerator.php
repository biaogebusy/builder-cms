<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationInput;
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
  id: 'image_classification_generator',
  title: new TranslatableMarkup('Image Classification Explorer'),
  description: new TranslatableMarkup('Contains a form where you can experiment and test the AI image classification.'),
)]
final class ImageClassificationGenerator extends AiApiExplorerPluginBase {

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
    return $this->providerManager->hasProvidersForOperationType('image_classification');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Get the query string for provider_id, model_id.
    $request = $this->getRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('image_class_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('image_class_ai_model', $request->query->get('model_id'));
    }

    $form = $this->getFormTemplate($form, 'image-classify-response');

    $form['left']['image'] = [
      '#type' => 'file',
      '#accept' => '.jpg, .jpeg, .png',
      '#title' => $this->t('Upload your image here. When submitted, your provider will generate a classification. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    $form['left']['labels'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Labels'),
      '#description' => $this->t('New line separated list of labels to filter the classification if the model takes it.'),
      '#attributes' => [
        'placeholder' => "is_hotdog\nis_not_hotdog\n",
      ],
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, 'image_classification', 'image_class', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['image_class_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Classify Image'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => 'image-classify-response',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'image_classification', 'image_class');
      $image_file = $this->generateFile('image');

      if (!$image_file) {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Image Provided'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please upload an image file (JPG, JPEG, or PNG) to classify.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      // Get the labels.
      $labels = explode("\n", $form_state->getValue('labels'));
      $labels = array_map('trim', $labels);
      // Make sure we don't have empty labels.
      $labels = array_filter($labels, function ($label) {
        return !empty(trim($label));
      });

      $input = new ImageClassificationInput($image_file, $labels);
      $classification = $provider->imageClassification($input, $form_state->getValue('image_class_ai_model'), ['ai_api_explorer'])->getNormalized();

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

        $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($provider, $form_state, $image_file->getFilename(), $labels);
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
            '#value' => $this->t('The provider did not generate a classification. Please check your image and try again.'),
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
   * @param string[] $labels
   *   The labels.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $filename, array $labels): array {
    // Generation code.
    $code = $this->getCodeExampleTemplate();
    $code['code']['#value'] .= '$binary = file_get_contents("' . $filename . '");<br>';
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    }
    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('image_class_ai_provider') . '\');<br>';
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    }
    $code['code']['#value'] .= "// Normalize the input.<br>";
    $code['code']['#value'] .= "\$image = new \Drupal\ai\OperationType\GenericType\ImageFile(\$binary, 'image/jpeg', '" . $filename . "');<br>";

    if (count($labels)) {
      $code['code']['#value'] .= "\$labels = [<br>";
      foreach ($labels as $label) {
        $code['code']['#value'] .= '&nbsp;&nbsp;"' . $label . '",<br>';
      }
      $code['code']['#value'] .= '];<br>';
      $code['code']['#value'] .= "\$input = new \Drupal\ai\OperationType\ImageClassification\ImageClassificationInput(\$image, \$labels);<br><br>";
    }
    else {
      $code['code']['#value'] .= "\$input = new \Drupal\ai\OperationType\ImageClassification\ImageClassificationInput(\$image);<br><br>";
    }
    $code['code']['#value'] .= "// Run the classification.<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->imageClassification(\$input, '" . $form_state->getValue('image_class_ai_model') . '\', ["your_module_name"]);<br><br>';
    $code['code']['#value'] .= "// Output is an array of classification objects.<br>";
    $code['code']['#value'] .= "\$classifications = \$response->getNormalized();<br>";

    return $code;
  }

}
