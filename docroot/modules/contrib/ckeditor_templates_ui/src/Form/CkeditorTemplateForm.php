<?php

namespace Drupal\ckeditor_templates_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implement config form for Ckeditor template.
 */
class CkeditorTemplateForm extends EntityForm {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, FileSystemInterface $file_system) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $template = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $template->label(),
      '#description' => $this->t('Your Template title'),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $template->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$template->isNew(),
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#default_value' => $template->getDescription(),
      '#description' => $this->t('Your Template description'),
    ];
    $icon = $template->get('icon');
    if ($icon) {
      $icon_markup = '<div class="form-item image-preview" style="max-width: 200px; max-height: 200px;">';
      $icon_markup .= '<img src="' . \Drupal::service('file_url_generator')->generateAbsoluteString($icon) . '" alt="' . $this->t('Preview') . '" />';
      $icon_markup .= '</div>';
      $form['icon_preview'] = [
        '#type' => 'inline_template',
        '#template' => $icon_markup,
      ];
    }
    $form['icon'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon path for this template'),
      '#default_value' => $icon,
      '#description' => $this->t('Examples: public://test.png, modules/my_module/test.png, themes/my_theme/test.png, //example.com/test.jpg'),
    ];
    $form['icon_upload'] = [
      '#title' => $this->t('Upload icon for this template'),
      '#type' => 'managed_file',
      '#description' => $this->t('You can use this field if you need to upload the file to the server. Allowed extensions: gif png jpg jpeg.'),
      '#upload_location' => 'public://ckeditor-templates',
      '#upload_validators' => [
        'file_validate_is_image' => [],
        'file_validate_extensions' => ['gif png jpg jpeg'],
        'file_validate_size' => [25600000],
      ],
    ];
    $form['html'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Body'),
      '#description' => $this->t('The predefined ckeditor template body'),
      '#required' => TRUE,
    ];
    if (!$template->isNew()) {
      $form['html']['#format'] = $template->getHtml()['format'];
      $form['html']['#default_value'] = $template->getHtml()['value'];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Check for a new uploaded icon.
    if (!$form_state->getErrors()) {
      $file = _file_save_upload_from_form($form['icon_upload'], $form_state, 0);
      if ($file) {
        // Put the temporary file in form_values so we can save it on submit.
        $form_state->setValue('icon_upload', $file);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $template = $this->entity;

    $file = $form_state->getValue('icon_upload');
    if ($file) {
      $file = File::load($file[0]);
      $file->setPermanent();
      $file->save();
      $template->set('icon', $file->getFileUri());
    }

    $status = $template->save();

    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label Ckeditor Template.', [
        '%label' => $template->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label Ckeditor Template was not saved.', [
        '%label' => $template->label(),
      ]));
    }

    $form_state->setRedirect('entity.ckeditor_template.collection');
  }

  /**
   * Helper function to check if ckeditor_template configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('ckeditor_template')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
