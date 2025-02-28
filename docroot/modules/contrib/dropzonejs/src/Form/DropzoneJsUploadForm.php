<?php

namespace Drupal\dropzonejs\Form;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dropzonejs\DropzoneJsUploadSaveInterface;
use Drupal\media_library\Form\FileUploadForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a form to create media entities from uploaded files.
 */
class DropzoneJsUploadForm extends FileUploadForm {

  /**
   * DropzoneJS module upload save service.
   *
   * @var \Drupal\dropzonejs\DropzoneJsUploadSaveInterface
   */
  protected $dropzoneJsUploadSave;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    $form->setDropzoneJsUploadSave($container->get('dropzonejs.upload_save'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->getBaseFormId() . '_dropzonejs';
  }

  /**
   * Set the upload service.
   *
   * @param \Drupal\dropzonejs\DropzoneJsUploadSaveInterface $dropzoneJsUploadSave
   *   The upload service.
   */
  protected function setDropzoneJsUploadSave(DropzoneJsUploadSaveInterface $dropzoneJsUploadSave) {
    $this->dropzoneJsUploadSave = $dropzoneJsUploadSave;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state) {
    $form = parent::buildInputElement($form, $form_state);
    // Create a file item to get the upload validators.
    $media_type = $this->getMediaType($form_state);
    $item = $this->createFileItem($media_type);
    $settings = $item->getFieldDefinition()->getSettings();
    $slots = $form['container']['upload']['#cardinality'];
    $process = (array) $this->elementInfo->getInfoProperty('dropzonejs', '#process', []);
    $form['container']['upload']['#type'] = 'dropzonejs';
    $form['container']['upload']['#process'] = array_merge(['::validateUploadElement'], $process);
    $translation = \Drupal::translation();
    $cardinality = $slots < 1 ? 0 : $slots;
    $dropzone_description = $translation->formatPlural($cardinality, 'Drop file here to upload it', 'Drop files here to upload them');
    $dropzone_specific_properties = [
      '#max_files' => $slots < 1 ? 0 : $slots,
      '#max_filesize' => $settings['max_filesize'],
      '#extensions' => $settings['file_extensions'],
      '#dropzone_description' => $dropzone_description,
    ];
    $form['container']['upload'] += $dropzone_specific_properties;

    $form['auto_select_handler'] = [
      '#type' => 'hidden',
      '#name' => 'auto_select_handler',
      '#id' => 'auto_select_handler',
      '#attributes' => ['id' => 'auto_select_handler'],
      '#submit' => ['::uploadButtonSubmit'],
      '#executes_submit_callback' => TRUE,
      '#ajax' => [
        'callback' => '::updateFormCallback',
        'wrapper' => 'media-library-wrapper',
        'event' => 'auto_select_media_library_widget',
        // Add a fixed URL to post the form since AJAX forms are automatically
        // posted to <current> instead of $form['#action'].
        // @todo Remove when https://www.drupal.org/project/drupal/issues/2504115
        // is fixed.
        // Follow along with changes in \Drupal\media_library\Form\OEmbedForm.
        'url' => Url::fromRoute('media_library.ui'),
        'options' => [
          'query' => $this->getMediaLibraryState($form_state)->all() + [
              FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
            ],
        ],
      ],
    ];

    $form['#attached']['library'][] = 'dropzonejs/widget';
    $form['#attached']['library'][] = 'dropzonejs/media_library';

    return $form;
  }

  /**
   * Validates the upload element.
   *
   * @param array $element
   *   The upload element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The processed upload element.
   */
  public function validateUploadElement(array $element, FormStateInterface $form_state) {
    if ($form_state::hasAnyErrors()) {
      // When an error occurs during uploading files, remove all files so the
      // user can re-upload the files.
      $element['#value'] = [];
    }
    $values = $form_state->getValue('upload', []);
    if (count($values['uploaded_files']) > $element['#cardinality'] && $element['#cardinality'] !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      $form_state->setError($element, $this->t('A maximum of @count files can be uploaded.', [
        '@count' => $element['#cardinality'],
      ]));
      $form_state->setValue('upload', []);
      $element['#value'] = [];
    }
    return $element;
  }

  /**
   * Submit handler for the upload button, inside the managed_file element.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function uploadButtonSubmit(array $form, FormStateInterface $form_state) {
    $files = $this->getFiles($form, $form_state);
    $this->processInputValues($files, $form, $form_state);
  }

  /**
   * Gets uploaded files.
   *
   * We implement this to allow child classes to operate on different entity
   * type while still having access to the files in the validate callback here.
   *
   * @param array $form
   *   Form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return \Drupal\file\FileInterface[]
   *   Array of uploaded files.
   */
  protected function getFiles(array $form, FormStateInterface $form_state) {
    // Create a file item to get the upload validators.
    $media_type = $this->getMediaType($form_state);
    $item = $this->createFileItem($media_type);

    $settings = $item->getFieldDefinition()->getSettings();

    $additional_validators = ['file_validate_size' => [Bytes::toNumber($settings['max_filesize']), 0]];

    $files = $form_state->get(['dropzonejs', $this->getFormId(), 'files']);

    if (!$files) {
      $files = [];
    }

    // We do some casting because $form_state->getValue() might return NULL.
    foreach ((array) $form_state->getValue(['upload', 'uploaded_files'], []) as $file) {
      if (file_exists($file['path'])) {
        $entity = $this->dropzoneJsUploadSave->createFile(
          $file['path'],
          // Need to leave destination empty, because Media Library will handle
          // the moving of the file to proper location.
          '',
          $settings['file_extensions'],
          $this->currentUser(),
          $additional_validators
        );
        if ($entity) {
          $files[] = $entity;
        }
      }
    }

    if ($form['container']['upload']['#max_files']) {
      $files = array_slice($files, -$form['container']['upload']['#max_files']);
    }

    $form_state->set(['dropzonejs', $this->getFormId(), 'files'], $files);

    return $files;
  }

}
