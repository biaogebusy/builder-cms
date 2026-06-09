<?php

namespace Drupal\ai_ckeditor;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface;
use Drupal\ai_ckeditor\Traits\AiCKEditorConfigTrait;
use Drupal\editor\Ajax\EditorDialogSave;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base plugin for AiCKEditorPlugin plugins.
 */
abstract class AiCKEditorPluginBase extends PluginBase implements AiCKEditorPluginInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use AiCKEditorConfigTrait;

  /**
   * The provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AiProviderPluginManager $ai_provider_manager, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $account, RequestStack $requestStack, LoggerChannelFactoryInterface $logger_factory, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->aiProviderManager = $ai_provider_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->account = $account;
    $this->requestStack = $requestStack;
    $this->loggerFactory = $logger_factory;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * Gets label for generate button.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The button label.
   */
  protected function getGenerateButtonLabel() {
    return $this->t('Generate');
  }

  /**
   * Gets selected text field label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The form element label.
   */
  protected function getSelectedTextLabel() {
    return $this->t('Selected text to process');
  }

  /**
   * Gets ai response field label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The form element label.
   */
  protected function getAiResponseLabel() {
    return $this->t('Response from AI');
  }

  /**
   * Gets ai response field description.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The form element description.
   */
  protected function getAiResponseDescription() {
    return $this->t('The response from AI will appear here. You can edit and tweak the response before saving it back to the main editor.');
  }

  /**
   * Returns whether selected text is needed for this plugin to work.
   *
   * @return bool
   *   The boolean flag.
   */
  protected function needsSelectedText() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    $editor_id = $this->requestStack->getParentRequest()->get('editor_id');
    $storage = $form_state->getStorage();
    if ($this->needsSelectedText()) {
      if (empty($storage['selected_text'])) {
        return [
          '#markup' => '<p>' . $this->t('You must select some text before you can summarize it.') . '</p>',
        ];
      }
    }

    $form['description'] = [
      '#markup' => '<p>' . $this->pluginDefinition['description'] . '</p>',
      '#weight' => -9999,
    ];
    if ($this->needsSelectedText()) {
      $selected_text = $storage['selected_text'] ?? '';
      if (is_string($selected_text) && $selected_text !== '') {
        $selected_text = Html::decodeEntities($selected_text);
        $selected_text = str_replace("\xC2\xA0", ' ', $selected_text);
      }

      $form['selected_text'] = [
        '#type' => 'textarea',
        '#title' => $this->getSelectedTextLabel(),
        '#disabled' => TRUE,
        '#default_value' => $selected_text,
        '#weight' => 5,
      ];
    }

    // Create a container for the generate button to keep it in the form.
    $form['generate_actions'] = [
      '#type' => 'container',
      // Lower weight to ensure it appears before results.
      '#weight' => 15,
      '#attributes' => [
        'class' => ['ai-ckeditor-generate-actions'],
      ],
    ];

    $form['generate_actions']['generate'] = [
      '#type' => 'button',
      '#value' => $this->getGenerateButtonLabel(),
      '#ajax' => [
        'callback' => [$this, 'ajaxGenerate'],
        'wrapper' => 'ai-ckeditor-response',
      ],
    ];

    $form['response_wrapper'] = [
      '#type' => 'container',
      // Position after generate button.
      '#weight' => 20,
      '#attributes' => [
        'id' => 'ai-ckeditor-response',
      ],
    ];

    $form['response_wrapper']['response_text'] = [
      '#type' => 'text_format',
      '#title' => $this->getAiResponseLabel(),
      '#description' => $this->getAiResponseDescription(),
      '#default_value' => '',
      '#allowed_formats' => [$editor_id],
      '#format' => $editor_id,
      // Automatically enable the CKEditor5 sourceEditing plugin for the
      // response text textarea, since various plugins require this.
      '#ai_ckeditor_response' => TRUE,
    ];

    // Lower actions section for the submit button positioned at the bottom.
    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 9999,
    ];

    $form['actions']['submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Save changes to editor'),
      '#weight' => 9999,
      '#ajax' => [
        'callback' => [$this, 'submitCkEditorModalForm'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitCkEditorModalForm(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $values = $form_state->getValues();

    // @todo Let plugins define an option to return HTML/Markdown or not and signal that to AI.
    $response->addCommand(new EditorDialogSave([
      'attributes' => [
        'value' => $values["plugin_config"]["response_wrapper"]["response_text"]["value"],
        'returnsHtml' => TRUE,
      ],
    ]));

    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function availableEditors() {
    return [
      $this->pluginId  => $this->label(),
    ];
  }

}
