<?php

namespace Drupal\ckeditor_templates\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\editor\EditorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Component\Plugin\PluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dialog form for allowing users to select a template.
 */
class CKEditorTemplatesDialogForm extends FormBase {

  /**
   * The template plugin manager.
   *
   * @var PluginManagerInterface
   */
  protected $ckeditorTemplateManager;

  /**
   * The available templates.
   *
   * @var array
   */
  protected array $templates;

  /**
   * Determines wheter the available templates need to beloaded.
   *
   * @var boolean
   */
  protected bool $templatesLoaded = FALSE;

  /**
   * The AJAX wrapper id.
   *
   * @var string
   */
  protected $ajaxWrapper = 'ckeditor-template-dialog-form--ajax-wrapper';

  /**
   * Create a new dialog instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(PluginManagerInterface $ckeditor_template_plugin_manager) {
    $this->ckeditorTemplateManager = $ckeditor_template_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): CKEditorTemplatesDialogForm | static {
    return new static(
      $container->get('plugin.manager.ckeditor_template')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ckeditor_templates__dialog_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EditorInterface $editor = NULL): array {
    $templates = [];

    // Gets the templates.
    foreach ($this->ckeditorTemplateManager->getTemplates() as $key => $template) {
      if (in_array($editor->id(), $template->allowedFormats())) {
        $templates[$key] = '
            <img src="' . $template->getThumb() . '" alt="' . $template->label() . '" />
            <div>
              <strong>' . $template->label() . ' </strong>
              <span>' . $template->getDescription() . '</span>
            </div>
          ';
      }
    }

    // Validate there are templates.
    if (empty($templates)) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('There is no template available for the <strong>@formatLabel</strong> text format.', [
          '@formatLabel' => $editor->label(),
        ]),
      ];

      return $form;
    }

    // List the templates.
    $form['label'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Select the template to open in the editor:'),
    ];

    $form['templates'] = [
      '#type' => 'radios',
      '#options' => $templates,
      '#wrapper_attributes' => [
        'class' => ['form-radios--templates'],
      ],
    ];

    $settings = $editor->getSettings();
    $replace_content = $settings['plugins']['ckeditor_templates_plugin']['replace_content'] ?? FALSE;
    $form['replace_content'] = [
      '#title' => $this->t('Replace actual contents'),
      '#type' => 'checkbox',
      '#default_value' => $replace_content,
      '#description' => $this->t('Remove the actual contents, keeping only the selected template.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Insert'),
        '#ajax' => [
          'callback' => [$this, 'ajaxSubmitForm'],
          'wrapper' => $this->ajaxWrapper,
          'disable-refocus' => TRUE,
        ],
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback function for inserting HTML code into the CKEditor.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   AJAX response.
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state): AjaxResponse | array {
    $response = new AjaxResponse();

    $template_id = $form_state->getValue('templates');
    if (isset($template_id)) {
      $html_code = $this->ckeditorTemplateManager->createInstance($template_id)->getHtml();
      if (!empty($html_code)) {
        $response->addCommand(new EditorDialogSave([
          'htmlCode' => $html_code,
          'replace' => $form_state->getValue('replace_content'),
        ]));
      }
    }

    $response->addCommand(new CloseModalDialogCommand());

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This function is required by Drupal, but the request is being
    // handled in the ajaxSubmitForm() function.
  }

}
