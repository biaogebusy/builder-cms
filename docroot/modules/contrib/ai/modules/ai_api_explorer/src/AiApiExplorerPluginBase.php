<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\FileBaseInterface;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base class for ai_api_explorer plugins.
 */
abstract class AiApiExplorerPluginBase extends PluginBase implements AiApiExplorerInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected RequestStack $requestStack, protected AiProviderFormHelper $aiProviderHelper, protected ExplorerHelper $explorerHelper, protected AiProviderPluginManager $providerManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
    );
  }

  /**
   * Gets the request object.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request object.
   */
  protected function getRequest(): Request {
    return $this->requestStack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): TranslatableMarkup {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['title'];
  }

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {

    // Default to TRUE and allow other plugins to override if they require.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function hasAccess(AccountInterface $account): bool {
    if ($account->hasPermission('access ai prompt')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getAjaxResponseId(): string {
    return '::ajaxResponse';
  }

  /**
   * {@inheritDoc}
   */
  public function getCodeExampleTemplate(): array {
    return [
      '#type' => 'details',
      '#title' => $this->t('Code Example'),
      '#open' => FALSE,
      '#attributes' => [
        '#class' => [
          'ai-code-wrapper',
        ],
      ],
      'code' => [
        '#type' => 'html_tag',
        '#tag' => 'code',
        '#value' => '',
        '#attributes' => [
          'style' => 'display: block; white-space: pre-wrap; padding: 20px;',
        ],
      ],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormTemplate(array $form, string $ajax_id, string $layout = 'two_columns'): array {
    $form['left'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          ($layout == 'two_columns') ? 'ai-left-side' : 'ai-three-info',
        ],
      ],
    ];

    if ($layout !== 'two_columns') {
      $form['middle'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => $ajax_id,
          'class' => [
            'ai-three-middle',
          ],
        ],
      ];
    }

    $form['right'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          ($layout == 'two_columns') ? 'ai-right-side' : 'ai-three-info',
        ],
      ],
    ];

    if ($layout == 'two_columns') {
      $form['right']['#attributes']['id'] = $ajax_id;
      $response_element = &$form['right'];
    }
    else {
      $response_element = &$form['middle'];
    }

    $response_element['response'] = [
      '#type' => 'inline_template',
      '#template' => '{{ ai_response }}',
      '#weight' => 1000,
      '#context' => [
        'ai_response' => [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h2',
            '#value' => $this->t('Response will appear here.'),
          ],
        ],
      ],
    ];

    $form['markup_end'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'ai-break',
        ],
      ],
      '#weight' => 1001,
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generateFile(string $type = 'audio'): FileBaseInterface|null {
    $return = NULL;

    $files = $this->getRequest()->files->all();
    if ($file = reset($files)) {

      if ($type == 'audio') {
        $file = (array_key_exists('file', $file)) ? $file['file'] : NULL;
      }
      else {
        $file = (array_key_exists($type, $file)) ? $file[$type] : NULL;
      }

      if ($file) {
        $mime_type = $file->getMimeType();
        $raw_file = file_get_contents($file->getPathname());
        $file_name = $file->getClientOriginalName();

        // If mimetype is application/octet-stream, we need to guess the type.
        if ($mime_type == 'application/octet-stream') {
          // Get the file extension from the filename.
          $extension = pathinfo($file_name, PATHINFO_EXTENSION);
          // Guess the mime type based on the extension.
          $mime_type = match ($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
          };
        }

        if ($type == 'audio') {
          $return = new AudioFile($raw_file, $mime_type, $file_name);
        }
        elseif ($type == 'image') {
          $return = new ImageFile($raw_file, $mime_type, $file_name);
        }
      }
    }

    return $return;
  }

  /**
   * {@inheritDoc}
   */
  public function addProviderCodeExample(AiProviderInterface|ProviderProxy $provider):string {
    $code = '$config = [<br>';
    foreach ($provider->getConfiguration() as $key => $value) {
      if (is_string($value)) {
        $code .= '&nbsp;&nbsp;"' . $key . '" => "' . $value . '",<br>';
      }
      elseif (is_array($value)) {
        $code .= '&nbsp;&nbsp;"' . $key . '" => ' . var_export($value, TRUE) . ',<br>';
      }
      else {
        $code .= '&nbsp;&nbsp;"' . $key . '" => ' . $value . ',<br>';
      }
    }
    $code .= '];<br><br>';

    return $code;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

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
    $prefix = $form_state->getTriggeringElement()['#ajax']['data-prefix'];
    $form_state->setRebuild();
    return $form['left'][$prefix . 'ajax_prefix'];
  }

}
