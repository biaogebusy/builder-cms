<?php

namespace Drupal\dropzonejs;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles files uploaded by Dropzone.
 *
 * The uploaded file will be stored in the configured tmp folder and will be
 * added a tmp extension. Further filename processing will be done in
 * Drupal\dropzonejs\Element::valueCallback. This means that the final
 * filename will be provided only after that callback.
 */
class UploadHandler implements UploadHandlerInterface {

  use StringTranslationTrait;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   *   The HTTP request object.
   */
  protected $request;

  /**
   * Transliteration service.
   *
   * @var \Drupal\Core\Transliteration\PhpTransliteration
   */
  protected $transliteration;

  /**
   * Language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The settings of dropzonejs.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $dropzoneSettings;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs dropzone upload controller route controller.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   Transliteration service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   LanguageManager service.
   */
  public function __construct(RequestStack $request_stack, ConfigFactoryInterface $config_factory, TransliterationInterface $transliteration, LanguageManagerInterface $language_manager, EventDispatcherInterface $event_dispatcher) {
    $this->request = $request_stack->getCurrentRequest();
    $this->transliteration = $transliteration;
    $this->languageManager = $language_manager;
    $this->dropzoneSettings = $config_factory->get('dropzonejs.settings');
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(UploadedFile $file) {
    $original_name = $file->getClientOriginalName();

    // There should be a filename and it should not contain a semicolon,
    // which we use to separate filenames.
    if (!isset($original_name)) {
      throw new UploadException(UploadException::FILENAME_ERROR);
    }

    // If the transliteration option is still there, we cannot use core
    // file event, because we don't know if we want to always transliterate
    // or just in dropzone.
    // @todo This can be removed, when we drop legacy support.
    $dropzone_transliteration = $this->dropzoneSettings->get('filename_transliteration');
    if ($dropzone_transliteration == TRUE) {
      // Transliterate.
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      $filename = $this->transliteration->transliterate($original_name, $langcode, '');

      // Replace whitespace.
      $filename = str_replace(' ', '_', $filename);
      // Remove remaining unsafe characters.
      $filename = preg_replace('![^0-9A-Za-z_.-]!', '', $filename);
      // Remove multiple consecutive non-alphabetical characters.
      $filename = preg_replace('/(_)_+|(\.)\.+|(-)-+/', '\\1\\2\\3', $filename);
      // Force lowercase to prevent issues on case-insensitive file systems.
      $filename = strtolower($filename);

      return $filename . '.txt';
    }

    // Dispatch the event so core can sanitize and transliterate (depending on
    // configuration) the filename.
    $event = new FileUploadSanitizeNameEvent($original_name, '');
    $this->eventDispatcher->dispatch($event);
    $filename = $event->getFilename();

    // For security reasons append the txt extension. It will be removed in
    // Drupal\dropzonejs\Element::valueCallback when we will know the valid
    // extension and we will be able to properly sanitize the filename.
    return $filename . '.txt';
  }

  /**
   * {@inheritdoc}
   */
  public function handleUpload(UploadedFile $file) {

    $error = $file->getError();
    if ($error != UPLOAD_ERR_OK) {
      // Check for file upload errors and return FALSE for this file if a lower
      // level system error occurred. For a complete list of errors:
      // See http://php.net/manual/features.file-upload.errors.php.
      switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
          $message = $this->t('The file could not be saved because it exceeds the maximum allowed size for uploads.');
          break;

        case UPLOAD_ERR_PARTIAL:
        case UPLOAD_ERR_NO_FILE:
          $message = $this->t('The file could not be saved because the upload did not complete.');
          break;

        // Unknown error.
        default:
          $message = $this->t('The file could not be saved. An unknown error has occurred.');
          break;
      }

      throw new UploadException(UploadException::FILE_UPLOAD_ERROR, $message);
    }

    // Open temp file.
    $tmp = $this->dropzoneSettings->get('tmp_upload_scheme') . '://' . $this->getFilename($file);
    if (!($out = fopen($tmp, $this->request->request->get('chunk', 0) ? 'ab' : 'wb'))) {
      throw new UploadException(UploadException::OUTPUT_ERROR);
    }

    // Read binary input stream.
    $input_uri = $file->getFileInfo()->getRealPath();
    if (!($in = fopen($input_uri, 'rb'))) {
      throw new UploadException(UploadException::INPUT_ERROR);
    }

    // Append input stream to temp file.
    while ($buff = fread($in, 4096)) {
      fwrite($out, $buff);
    }

    // Be nice and keep everything nice and clean. Initial uploaded files are
    // automatically removed by PHP at the end of the request so we don't need
    // to do that.
    // @todo when implementing multipart don't forget to drupal_unlink.
    fclose($in);
    fclose($out);

    return $tmp;
  }

}
