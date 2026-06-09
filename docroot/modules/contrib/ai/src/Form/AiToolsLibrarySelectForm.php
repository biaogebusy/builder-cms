<?php

namespace Drupal\ai\Form;

use Drupal\ai\AiToolsLibraryState;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class for AI tools library selection.
 */
class AiToolsLibrarySelectForm extends FormBase {

  /**
   * The function group plugin manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionGroupPluginManager
   */
  protected PluginManagerInterface $functionGroupPluginManager;

  /**
   * The function call (tools) plugin manager.
   *
   * @var \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   */
  protected PluginManagerInterface $functionCallPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->functionGroupPluginManager = $container->get('plugin.manager.ai.function_groups');
    $instance->functionCallPluginManager = $container->get('plugin.manager.ai.function_calls');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_tools_library_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AiToolsLibraryState $state = NULL) {
    $form = [
      '#attributes' => [
        'class' => [
          'ai-tools-library-view__rows',
        ],
      ],
    ];
    $form['filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter'),
      '#description' => $this->t('Filter the tools by name.'),
      '#attributes' => [
        'autocomplete' => 'off',
        'class' => [
          'tools-filter',
        ],
      ],
      '#weight' => -10000,
    ];
    $form['tools'] = [
      '#tree' => TRUE,
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'ai-tools-selection',
        ],
      ],
    ];
    $tools_count = 0;
    $selected_group = $state->getSelectedGroupId();
    foreach ($this->functionCallPluginManager->getDefinitions() as $plugin_id => $definition) {
      $group = $definition['group'];
      if ($selected_group == '_all' || ($group && $group == $selected_group && $this->functionGroupPluginManager->hasDefinition($group))) {
        $form['tools']['tool__' . $plugin_id] = [
          '#prefix' => '<div class="ai-tools-library-item ai-tools-library-item--grid" data-id="' . $plugin_id . '">',
          '#suffix' => '</div>',
          '#type' => 'checkbox',
          '#title' => $definition['name'],
          '#return_value' => $plugin_id,
          '#description' => $definition['description'],
        ];
        $tools_count++;
      }
    }
    if ($tools_count == 0) {
      $form['tools']['tool__none'] = [
        '#markup' => $this->t('There are no tools available in this group.'),
      ];
    }
    $selection_field_id = 'tools_selection';
    $form[$selection_field_id] = [
      '#type' => 'hidden',
      '#attributes' => [
        // This is used to identify the hidden field in the form via JavaScript.
        'id' => 'ai-tools-library-modal-selection',
      ],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => [
          'form-actions',
        ],
      ],
    ];
    $query = $state->all();
    $query[FormBuilderInterface::AJAX_FORM_REQUEST] = TRUE;
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Use selected tools'),
      '#button_type' => 'primary',
      '#field_id' => $selection_field_id,
      '#ajax' => [
        'url' => Url::fromRoute('ai.tools_library'),
        'options' => [
          'query' => $query,
        ],
        'callback' => [static::class, 'updateWidget'],
      ],
    ];
    return $form;
  }

  /**
   * Submit handler for the ai tools library select form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A command to send the selection to the current field widget.
   */
  public static function updateWidget(array &$form, FormStateInterface $form_state, Request $request) {
    $field_id = $form_state->getTriggeringElement()['#field_id'];
    $selected_ids = $form_state->getValue($field_id);
    $selected_ids = $selected_ids ? explode(',', $selected_ids) : [];
    // Allow the opener service to handle the selection.
    $state = AiToolsLibraryState::fromRequest($request);

    return \Drupal::service('ai_tools_library.opener.form_element')
      ->getSelectionResponse($state, $selected_ids)
      ->addCommand(new CloseDialogCommand());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
