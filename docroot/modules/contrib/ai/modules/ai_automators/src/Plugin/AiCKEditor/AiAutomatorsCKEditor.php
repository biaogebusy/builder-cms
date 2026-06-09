<?php

namespace Drupal\ai_automators\Plugin\AICKEditor;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Entity\File;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_automators\Service\Automate;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin to do AI completion.
 */
#[AiCKEditor(
  id: 'ai_automators_ckeditor',
  label: new TranslatableMarkup('AI Automators CKEditor'),
  description: new TranslatableMarkup('Chained workflows setup with AI Automators.'),
  module_dependencies: [],
)]
final class AiAutomatorsCKEditor extends AiCKEditorPluginBase {

  use StringTranslationTrait;

  /**
   * The automate service.
   *
   * @var \Drupal\ai_automators\Service\Automate
   */
  protected Automate $automate;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilderInterface
   */
  protected $entityFormBuilder;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AiProviderPluginManager $ai_provider_manager,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $account,
    RequestStack $requestStack,
    LoggerChannelFactoryInterface $logger_factory,
    Automate $automate,
    ConfigFactoryInterface $config_factory,
    EntityFieldManagerInterface $field_manager,
    FileUrlGeneratorInterface $file_url_generator,
    EntityFormBuilderInterface $entity_form_builder,
    LanguageManagerInterface $language_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $ai_provider_manager, $entity_type_manager, $account, $requestStack, $logger_factory, $language_manager);
    $this->automate = $automate;
    $this->configFactory = $config_factory;
    $this->fieldManager = $field_manager;
    $this->fileUrlGenerator = $file_url_generator;
    $this->entityFormBuilder = $entity_form_builder;
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
      $container->get('ai_automator.automate'),
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('file_url_generator'),
      $container->get('entity.form_builder'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'workflows' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Create checkboxes.
    foreach ($this->automate->getWorkflows() as $workflow_id => $workflow_label) {
      $form[$workflow_id . '_advanced'] = [
        '#type' => 'details',
        '#title' => $this->t('%label Settings', [
          '%label' => $workflow_label,
        ]),
        '#open' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="' . $workflow_id . '"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form[$workflow_id . '_advanced'][$workflow_id] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable %workflow', [
          '%workflow' => $workflow_label,
        ]),
        '#default_value' => $this->configuration['workflows'][$workflow_id]['enabled'] ?? FALSE,
      ];

      $form[$workflow_id . '_advanced']['inputs'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Inputs'),
        '#description' => $this->t('Select the inputs to use for this workflow that will be exposed to the person using CKEditor.'),
        '#options' => $this->automate->getRequiredFields($workflow_id),
        '#default_value' => $this->configuration['workflows'][$workflow_id]['inputs'] ?? [],
      ];

      $form[$workflow_id . '_advanced']['selected_input'] = [
        '#type' => 'select',
        '#title' => $this->t('Text Selection Input'),
        '#description' => $this->t('If the users marks text in the parent input, this field will be automatically filled in. If its a file field, a file has to be in the marked text.'),
        '#options' => $this->automate->getRequiredFields($workflow_id),
        '#empty_option' => $this->t('None'),
        '#default_value' => $this->configuration['workflows'][$workflow_id]['selected_input'] ?? '',
      ];

      $form[$workflow_id . '_advanced']['require_selection'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Require Selection'),
        '#description' => $this->t('If the user has to select text in the parent editor to use this workflow.'),
        '#default_value' => $this->configuration['workflows'][$workflow_id]['require_selection'] ?? FALSE,
      ];

      $form[$workflow_id . '_advanced']['write_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Write Mode'),
        '#description' => $this->t('Select the write mode for this workflow.'),
        '#options' => [
          'append' => $this->t('Append'),
          'prepend' => $this->t('Prepend'),
          'replace' => $this->t('Replace'),
        ],
        '#default_value' => $this->configuration['workflows'][$workflow_id]['write_mode'] ?? 'replace',
      ];

      $form[$workflow_id . '_advanced']['output'] = [
        '#type' => 'select',
        '#title' => $this->t('Output'),
        '#description' => $this->t('Select the output to use for this workflow that will fill out the content.'),
        '#options' => $this->automate->getAutomatedFields($workflow_id, [
          'text_long',
          'image',
        ]),
        '#default_value' => $this->configuration['workflows'][$workflow_id]['output'] ?? '',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function needsSelectedText() {
    return FALSE;
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
    foreach ($this->automate->getWorkflows() as $workflow_id => $workflow_label) {
      $this->configuration['workflows'][$workflow_id]['enabled'] = $form_state->getValue($workflow_id . '_advanced')[$workflow_id];
      $this->configuration['workflows'][$workflow_id]['inputs'] = $form_state->getValue($workflow_id . '_advanced')['inputs'];
      $this->configuration['workflows'][$workflow_id]['output'] = $form_state->getValue($workflow_id . '_advanced')['output'];
      $this->configuration['workflows'][$workflow_id]['selected_input'] = $form_state->getValue($workflow_id . '_advanced')['selected_input'];
      $this->configuration['workflows'][$workflow_id]['require_selection'] = $form_state->getValue($workflow_id . '_advanced')['require_selection'];
      $this->configuration['workflows'][$workflow_id]['write_mode'] = $form_state->getValue($workflow_id . '_advanced')['write_mode'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    $form_state->disableCache();
    $storage = $form_state->getStorage();
    $form = parent::buildCkEditorModalForm($form, $form_state);
    unset($form['selected_text']);
    unset($form['#markup']);

    // Something is wrong with the settings if we don't get the ids.
    if (!isset($settings['config_id']) || !isset($settings['editor_id']) || !isset($settings['plugin_id'])) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    // Check that the settings exists.
    $editor_config = $this->configFactory->get('editor.editor.' . $settings['editor_id']);
    if (empty($editor_config->get('settings'))) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    // Since this is custom form outside the CKEditor5 context, we have to
    // check that the user has permissions to this specific text format.
    if (!$this->account->hasPermission('use text format ' . $editor_config->get('format'))) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    $instance_config = $editor_config->get('settings')['plugins']['ai_ckeditor_ai'] ?? [];

    // Make sure its enabled.
    if (empty($instance_config['plugins'][$settings['plugin_id']]['workflows'][$settings['config_id']]['enabled'])) {
      return [
        '#markup' => '<p>' . $this->t('This AI Automator is not enabled. Please enable it in the settings.') . '</p>',
      ];
    }

    // Get the configuration.
    $plugin_config = $instance_config['plugins'][$settings['plugin_id']]['workflows'][$settings['config_id']];

    // If selection is required, make sure that there is a selection.
    if ($plugin_config['require_selection'] && empty($storage['selected_text'])) {
      return [
        '#markup' => '<p>' . $this->t('Please select text in the editor before using this assistant.') . '</p>',
      ];
    }
    // Get the fields.
    try {
      $fields = $this->fieldManager->getFieldDefinitions('automator_chain', $settings['config_id']);
    }
    catch (\Exception $e) {
      return [
        '#markup' => '<p>' . $this->t('Something went wrong. Please try again.') . '</p>',
      ];
    }

    // Metadata.
    $form['automator_chain'] = [
      '#type' => 'hidden',
      '#value' => $settings['config_id'],
    ];
    $form['config_id'] = [
      '#type' => 'hidden',
      '#value' => $settings['config_id'],
    ];
    $form['automator_output'] = [
      '#type' => 'hidden',
      '#value' => $plugin_config['output'],
    ];
    $form['automator_storage'] = [
      '#type' => 'hidden',
      '#value' => $storage['selected_text'],
    ];
    $form['automator_write_mode'] = [
      '#type' => 'hidden',
      '#value' => $plugin_config['write_mode'],
    ];

    // Get the inputs.
    foreach ($plugin_config['inputs'] as $input => $value) {
      // Use the entity field.
      if (isset($fields[$input]) && in_array($fields[$input]->getType(), [
        'image',
        'file',
      ])) {
        $form[$input] = [
          '#type' => 'managed_file',
          '#title' => $this->t('Upload a file'),
          '#description' => $this->t('Allowed types: jpg, jpeg, png.'),
          '#upload_location' => 'public://uploads/',
          '#value_callback' => [self::class, 'fileValueCallback'],
          '#submit' => [[self::class, 'saveUploadedFile']],
        ];
      }
      elseif (isset($fields[$input])) {
        $form[$input] = [
          '#type' => 'textarea',
          '#title' => $fields[$input]->getLabel(),
          '#description' => $fields[$input]->getDescription(),
        ];
      }
    }

    if (!empty($plugin_config['selected_input']) && !empty($storage['selected_text'])) {
      $file_storage = $this->entityTypeManager->getStorage('file');
      switch ($fields[$plugin_config['selected_input']]->getType()) {
        case 'image':
        case 'file':
          // Try to extract the uuid from from the tag.
          $matches = [];
          preg_match('/data-entity-uuid="([^"]+)"/', $storage['selected_text'], $matches);
          if (!empty($matches[1])) {
            $file = $file_storage->loadByProperties(['uuid' => $matches[1]]);
            if (!empty($file)) {
              $file = reset($file);
              $form[$plugin_config['selected_input']]['#default_value'] = [$file->id()];
            }
          }
          break;

        default:
          $form[$plugin_config['selected_input']]['#value'] = $storage['selected_text'];
          $form[$plugin_config['selected_input']]['#default_value'] = $storage['selected_text'];
          break;
      }
    }
    $form['#attached']['library'][] = 'ai_automators/automator_ckeditor';

    return $form;
  }

  /**
   * Static value callback for managed_file.
   */
  public static function fileValueCallback(&$element, $input, FormStateInterface $form_state) {
    // Use default managed_file value callback to handle initial processing.
    $value = ManagedFile::valueCallback($element, $input, $form_state);

    // If a file was uploaded, save it and return only the file ID.
    if (!empty($value) && is_array($value) && !empty($value[0])) {
      return [$value[0]];
    }

    return $value;
  }

  /**
   * Static submit handler to save uploaded files and store only file IDs.
   */
  public static function saveUploadedFile(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue('plugin_config');
    $input = $form_state->getTriggeringElement()['#name'];
    if (!empty($values[$input]) && is_array($values[$input]) && !empty($values[$input][0])) {
      // Load the file entity from the file ID.
      $file = File::load($values[$input][0]);
      if ($file) {
        // Ensure the file is permanent and saved.
        $file->setPermanent();
        $file->save();
        // Update form state with only the file ID.
        $form_state->setValue(['plugin_config', $input], [$file->id()]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxGenerate(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // Generate the response.
    $values = $form_state->getValue('plugin_config');

    // Make sure that the automator chain exists.
    if (empty($values['automator_chain'])) {
      throw new \InvalidArgumentException('The automator chain is missing.');
    }
    $fields = $this->fieldManager->getFieldDefinitions('automator_chain', $values['automator_chain']);

    // Get the inputs.
    $inputs = [];
    foreach ($values as $key => $value) {
      if (!in_array($key, [
        'actions',
        'response_text',
        'automator_chain',
        'automator_output',
        'automator_storage',
        'automator_write_mode',
        'generate_actions',
        'response_wrapper',
        'config_id',
      ])) {
        // Only add fields that exist in the entity type.
        if (isset($fields[$key])) {
          if (in_array($fields[$key]->getType(), [
            'image',
            'file',
          ])) {
            $inputs[$key] = $value[0] ?? '';
          }
          else {
            $inputs[$key] = $value;
          }
        }
      }
    }
    $output = $this->automate->run($values['automator_chain'], $inputs);

    if (!isset($output[$values['automator_output']][0])) {
      throw new \Exception('The output field is missing.');
    }

    // Depending on the output type.
    switch ($fields[$values['automator_output']]->getType()) {
      case 'image':
        $result = $this->renderImage($output[$values['automator_output']][0]);
        break;

      default:
        $result = $output[$values['automator_output']][0]['value'];
        break;
    }

    if ($values['automator_write_mode'] == 'append') {
      $result = $result . $values['automator_storage'];
    }
    elseif ($values['automator_write_mode'] == 'prepend') {
      $result = $values['automator_storage'] . $result;
    }

    $response->addCommand(new InvokeCommand(
      '#ai-ckeditor-response',
      'automatorUpdateCkEditor',
      [$result],
    ));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function availableEditors() {
    $available_workflows = $this->automate->getWorkflows();
    $editors = [];
    foreach ($this->configuration['workflows'] as $workflow => $data) {
      if (!empty($data['enabled']) && isset($available_workflows[$workflow])) {
        $id = $this->getPluginId() . '__' . $workflow;
        $editors[$id] = $available_workflows[$workflow];
      }
    }
    return $editors;
  }

  /**
   * If the field is an image, render an image.
   *
   * @param array $data
   *   The image data id.
   *
   * @return string
   *   The image markup.
   */
  protected function renderImage(array $data): string {
    if (empty($data['target_id']) && empty($data['width']) && empty($data['height'])) {
      return $this->t('Could not generate image.');
    }
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($data['target_id']);
    if ($file) {
      $url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      return '<img data-entity-uuid="' . $file->uuid() . '" data-entity-type="file" src="' . $url . '" width="' . $data['width'] . '" height="' . $data['height'] . '" />';
    }
    return '';
  }

}
