<?php

namespace Drupal\ai_ckeditor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for displaying dialog options in CKEditor.
 */
class AiCKEditorDialogForm extends FormBase {

  /**
   * The AI CKEditor plugin manager.
   *
   * @var \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager
   */
  protected $aiCKEditorPluginManager;

  /**
   * The ajax wrapper id to use for re-rendering the form.
   *
   * @var string
   */
  protected $ajaxWrapper = 'ai-ckeditor-dialog-form-wrapper';

  /**
   * The form constructor.
   *
   * @param \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager $ai_ckeditor_plugin_manager
   *   The AI CKEditor plugin manager.
   */
  public function __construct(AiCKEditorPluginManager $ai_ckeditor_plugin_manager) {
    $this->aiCKEditorPluginManager = $ai_ckeditor_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai_ckeditor'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor5_ai_ckeditor_dialog_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    $payload = $request->getPayload();

    // Ensure 'editor_id' is provided.
    $editor_id = $payload->get('editor_id');

    if (!$editor_id) {
      throw new \InvalidArgumentException('We cannot determine that this is a CKEditor field.');
    }

    // Check that settings exist for this editor ID.
    $editor_config = $this->configFactory()->get('editor.editor.' . $editor_id);

    if (empty($editor_config->get('settings'))) {
      throw new \InvalidArgumentException('The editor configuration is empty.');
    }

    // Verify user permissions for the specific text format.
    if (!$this->currentUser()->hasPermission('use text format ' . $editor_config->get('format'))) {
      throw new \InvalidArgumentException('The user does not have permission to use this text format.');
    }

    // Ensure 'plugin_id' is provided, either from payload or form state.
    $plugin_id = $payload->get('plugin_id') ?? $form_state->getValue('plugin_id');

    if (!$plugin_id) {
      throw new \InvalidArgumentException('No action was selected.');
    }

    // Load plugin instance configuration.
    $instance_config = $editor_config->get('settings')['plugins']['ai_ckeditor_ai'] ?? [];
    $full_payload = $request->request->all();

    if (empty($instance_config['plugins'])) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No AI CKEditor plugins were detected.'),
      ];

      return $form;
    }

    if ($plugin_id) {
      try {
        // Check for multi-instance plugin format (e.g., "plugin__config").
        $config_id = $plugin_id;

        if (strpos($plugin_id, '__') !== FALSE) {
          [$plugin_id, $config_id] = explode('__', $plugin_id);
        }
        // The config id can also be in the payload of the plugin config.
        elseif (!empty($full_payload['plugin_config']['config_id'])) {
          $config_id = $full_payload['plugin_config']['config_id'];
        }

        // Instantiate the plugin using the manager.
        /** @var \Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface $instance */
        $instance = $this->aiCKEditorPluginManager->createInstance(
          $plugin_id,
          $instance_config['plugins'][$config_id] ?? []
        );

        // Initialize subform and SubformState.
        $subform = $form['plugin_config'] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);

        // Set selected text in storage if provided in the payload.
        $selected_text = $payload->get('selected_text');

        if ($selected_text) {
          $subform_state->setStorage(['selected_text' => $selected_text]);
        }

        // Build and render the plugin configuration form.
        $form['plugin_config'] = $instance->buildCkEditorModalForm([], $subform_state, [
          'config_id' => $config_id,
          'editor_id' => $editor_id,
          'plugin_id' => $plugin_id,
          'selected_text' => $selected_text,
        ]);
        $form['plugin_config']['#tree'] = TRUE;

        // Hidden fields for editor ID, plugin ID, and selected text.
        $form['editor_id'] = [
          '#type' => 'hidden',
          '#value' => $editor_id,
        ];
        $form['plugin_id'] = [
          '#type' => 'hidden',
          '#value' => $plugin_id,
        ];
        $form['selected_text'] = [
          '#type' => 'hidden',
          '#value' => $selected_text,
        ];
      }
      catch (\Exception $exception) {
        $form['message'] = [
          '#type' => 'status_messages',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
