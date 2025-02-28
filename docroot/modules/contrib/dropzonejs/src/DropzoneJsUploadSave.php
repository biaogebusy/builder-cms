<?php

namespace Drupal\dropzonejs;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;
use Drupal\file\FileInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * A service that saves files uploaded by the dropzonejs element as files.
 *
 * Most of this file mimics or directly copies what core does. For more
 * information and comments see file_save_upload().
 */
class DropzoneJsUploadSave implements DropzoneJsUploadSaveInterface {

  use StringTranslationTrait;

  /**
   * Entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Mime type guesser service.
   *
   * @var \Symfony\Component\Mime\MimeTypesInterface
   */
  protected $mimeTypeGuesser;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Construct the DropzoneUploadSave object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Symfony\Component\Mime\MimeTypesInterface $mimetype_guesser
   *   The mime type guesser service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, \Symfony\Component\Mime\MimeTypeGuesserInterface $mimetype_guesser, FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory, RendererInterface $renderer, ConfigFactoryInterface $config_factory, Token $token, MessengerInterface $messenger, StreamWrapperManagerInterface $stream_wrapper_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mimeTypeGuesser = $mimetype_guesser;
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('dropzonejs');
    $this->renderer = $renderer;
    $this->configFactory = $config_factory;
    $this->token = $token;
    $this->messenger = $messenger;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function createFile($uri, $destination, $extensions, AccountProxyInterface $user, array $validators = []) {
    // Create the file entity.
    $uri = $this->streamWrapperManager->normalizeUri($uri);
    $file_info = new \SplFileInfo($uri);

    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityTypeManager->getStorage('file')->create([
      'uid' => $user->id(),
      'status' => 0,
      'filename' => $file_info->getFilename(),
      'uri' => $uri,
      'filesize' => $file_info->getSize(),
      'filemime' => $this->mimeTypeGuesser->guessMimeType($uri),
    ]);

    // Replace tokens. As the tokens might contain HTML we convert it to plain
    // text.
    $destination = PlainTextOutput::renderFromHtml($this->token->replace($destination));

    // Handle potentialy dangerous extensions.
    $renamed = $this->renameExecutableExtensions($file);
    // The .txt extension may not be in the allowed list of extensions. We have
    // to add it here or else the file upload will fail.
    if ($renamed && !empty($extensions)) {
      $extensions .= ' txt';
      $this->messenger->addMessage($this->t('For security reasons, your upload has been renamed to %filename.', ['%filename' => $file->getFilename()]));
    }

    // Validate the file.
    $errors = $this->validateFile($file, $extensions, $validators);
    if (!empty($errors)) {
      $message = [
        'error' => [
          '#markup' => $this->t('The specified file %name could not be uploaded.', ['%name' => $file->getFilename()]),
        ],
        'item_list' => [
          '#theme' => 'item_list',
          '#items' => $errors,
        ],
      ];
      $this->messenger->addError($this->renderer->renderPlain($message));
      return FALSE;
    }

    if (!empty($destination)) {
      // Prepare destination.
      if (!$this->prepareDestination($file, $destination)) {
        $this->messenger->addError($this->t('The file could not be uploaded because the destination %destination is invalid.', ['%destination' => $destination]));
        return FALSE;
      }

      // Move uploaded files from PHP's upload_tmp_dir to destination.
      $move_result = $this->fileSystem->move($uri, $file->getFileUri());
      if (!$move_result) {
        $this->messenger->addError($this->t('File upload error. Could not move uploaded file.'));

        $this->logger->notice('Upload error. Could not move uploaded file %file to destination %destination.', [
          '%file' => $file->getFilename(),
          '%destination' => $file->getFileUri(),
        ]);
        return FALSE;
      }

      // Set the permissions on the new file.
      $this->fileSystem->chmod($file->getFileUri());
    }

    return $file;
  }

  /**
   * {@inheritdoc}
   */
  public function validateFile(FileInterface $file, $extensions, array $additional_validators = []) {
    $validators = $additional_validators;

    if (!empty($extensions)) {
      $validators['file_validate_extensions'] = [$extensions];
    }
    $validators['file_validate_name_length'] = [];

    // Call the validation functions specified by this function's caller.
    return file_validate($file, $validators);
  }

  /**
   * Rename potentially executable files.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity object.
   *
   * @return bool
   *   Whether the file was renamed or not.
   */
  protected function renameExecutableExtensions(FileInterface $file) {
    if (!$this->configFactory->get('system.file')->get('allow_insecure_uploads') && preg_match('/\.(php|pl|py|cgi|asp|js)(\.|$)/i', $file->getFilename()) && (substr($file->getFilename(), -4) != '.txt')) {
      $file->setMimeType('text/plain');
      // The destination filename will also later be used to create the URI.
      $file->setFilename($file->getFilename() . '.txt');
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Validate and set destination the destination URI.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity object.
   * @param string $destination
   *   A string containing the URI that the file should be copied to. This must
   *   be a stream wrapper URI.
   *
   * @return bool
   *   True if the destination was sucesfully validated and set, otherwise
   *   false.
   */
  protected function prepareDestination(FileInterface $file, $destination) {
    // Assert that the destination contains a valid stream.
    $destination_scheme = $this->streamWrapperManager::getScheme($destination);
    if (!$this->streamWrapperManager->isValidScheme($destination_scheme)) {
      return FALSE;
    }

    // Prepare the destination dir.
    if (!file_exists($destination)) {
      $this->fileSystem->mkdir($destination, NULL, TRUE);
    }

    // A file URI may already have a trailing slash or look like "public://".
    if (substr($destination, -1) != '/') {
      $destination .= '/';
    }
    $destination = $this->fileSystem->getDestinationFilename($destination . $file->getFilename(), FileSystemInterface::EXISTS_RENAME);
    $file->setFileUri($destination);
    return TRUE;
  }

}
