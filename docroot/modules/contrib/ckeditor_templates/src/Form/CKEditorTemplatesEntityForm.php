<?php

namespace Drupal\ckeditor_templates\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Psr\Container\ContainerInterface;

/**
 * CKEditor Template form.
 *
 * @property \Drupal\ckeditor_templates\CKEditorTemplatesInterface $entity
 */
class CKEditorTemplatesEntityForm extends EntityForm {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected FileUsageInterface $fileUsage;

  /**
   * Constructs a new entity form.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity type manager.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   The file usage service.
   */
  public function __construct(FileSystemInterface $file_system, EntityTypeManagerInterface $entity_type_manager, FileUsageInterface $file_usage) {
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileUsage = $file_usage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('entity_type.manager'),
      $container->get('file.usage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Gets the allowed format options.
    $allowedFormatOptions = [];
    foreach (filter_formats() as $format) {
      $editor = editor_load($format->id());
      if (isset($editor) && $editor->getEditor() === 'ckeditor5') {
        $allowedFormatOptions[$format->id()] = $format->label();
      }
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ckeditor_templates\Entity\CKEditorTemplates::load',
      ],
      '#disabled' => !$this->entity->isNew(),
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
    ];

    $form['thumb'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Illustrative Image/Icon'),
      '#default_value' => $this->entity->get('thumb'),
      '#description' => $this->t('Allowed types: png jpeg jpg gif'),
      '#upload_location' => 'public://ckeditor-templates',
      '#upload_validators' => [
        'file_validate_extensions' => ['gif png jpg jpeg'],
      ],
      '#cardinality' => 1,
    ];

    $form['thumb_alternative'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alternative Image/Icon'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->get('thumb_alternative'),
      '#description' => $this->t('Use this field as an alternative to uploading an illustrative image/icon. You can provide a URL or path to an image file (i.e., //domain.com/icon.png, public://icon.png, /modules/my_module/icon.png, /themes/my_theme/icon.png, etc.).'),
      '#required' => FALSE,
    ];

    $form['code'] = [
      '#type' => 'text_format',
      '#title' => $this->t('HTML Code'),
      '#description' => $this->t('The HTML code to be injected into the CKEditor.'),
      '#required' => TRUE,
    ];
    if (!$this->entity->isNew()) {
      $form['code']['#format'] = $this->entity->get('code')['format'];
      $form['code']['#default_value'] = $this->entity->get('code')['value'];
    }

    $form['formats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available For'),
      '#default_value' => $this->entity->get('formats') ?? [],
      '#options' => $allowedFormatOptions,
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $template = $this->entity;
    $status = $template->save();

    if ($status) {

      // Sets the image as permanent.
      try {
        $thumb = $form_state->getValue('thumb');
        if (!empty($thumb)) {
          $thumb = reset($thumb);

          /** @var  Drupal\file\Entity\File $file */
          $file = $this->entityTypeManager->getStorage('file')->load($thumb);
          if (isset($file)) {
            $file->setPermanent();
            $file->save();

            // Add the file to the usage calculation.
            $this->fileUsage->add($file, 'ckeditor_templates', 'media', $file->id());
          }
        }
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $this->logger('templates')->critical($e->getMessage());
      }

      $this->messenger()->addMessage($this->t('Saved the %label CKEditor Template.', [
        '%label' => $template->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label CKEditor Template was not saved.', [
        '%label' => $template->label(),
      ]));
    }

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $status;
  }

}
