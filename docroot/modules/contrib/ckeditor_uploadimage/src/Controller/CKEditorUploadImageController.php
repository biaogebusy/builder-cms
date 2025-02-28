<?php
/**
 * @file
 * Contains \Drupal\ckeditor_uploadimage\Controller\CKEditorUploadImageController.
 */

namespace Drupal\ckeditor_uploadimage\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\Bytes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Component\Utility\Environment;
use Drupal\Core\File\FileSystemInterface;

class CKEditorUploadImageController extends ControllerBase {
  /**
   * Save uploaded file via CKEditor uploadimage plugin.
   */
  public function saveFile(Request $request) {
    $status   = TRUE;
    $errorMsg = '';
    $defaultResponsiveImageStyle = '';
    $filterFormatId = $request->query->get('filterFormatId');
    $editor = editor_load($filterFormatId);
    // Construct strings to use in the upload validators.
    $imageUpload = $editor->getImageUploadSettings();
    if (!empty($imageUpload['max_dimensions']['width']) || !empty($imageUpload['max_dimensions']['height'])) {
      $maxDimensions = $imageUpload['max_dimensions']['width'] . 'x' . $imageUpload['max_dimensions']['height'];
    }
    else {
      $maxDimensions = 0;
    }
    $maxFilesize = min(Bytes::toInt($imageUpload['max_size']), Environment::getUploadMaxSize());
    $destination = $imageUpload['scheme'] . '://' . $imageUpload['directory'];
    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = \Drupal::service('file_system');
    if (isset($destination) && !$fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
      \Drupal::logger('ckeditor_uploadimage')->notice(
        'The upload directory %directory for the file field %name could not be
        created or is not accessible. A newly uploaded file could not be saved
        in this directory as a consequence, and the upload was canceled.', [
          '%directory' => $destination,
          '%name' => 'fid',
      ]);
      $errorMsg = $this->t('The file could not be uploaded.');
      $status  = FALSE;
    }
    else {
      $validators = [
        'file_validate_extensions' => ['gif png jpg jpeg'],
        'file_validate_size' => [$maxFilesize],
        'file_validate_image_resolution' => [$maxDimensions],
      ];
      $files = $request->files->get('files', array());
      if (!isset($files['fid'])) {
        $files = $request->files->all();
        $request->files->set('files', ['fid' => $files['upload']]);
      }
      $file = file_save_upload('fid', $validators, $destination);
      $messages = \Drupal::messenger()->all();
      if (isset($messages['error'])) {
        /** @var \Drupal\Core\Render\Markup $message */
        foreach ($messages['error'] as $message) {
          $errorMsg = '<div>' . $message->jsonSerialize() . '</div>';
        }
      }
      if (isset($messages['warning'])) {
        /** @var \Drupal\Core\Render\Markup $message */
        foreach ($messages['warning'] as $message) {
          $errorMsg .= '<div>' . $message->jsonSerialize() . '</div>';
        }
      }
      if (isset($messages['status'])) {
        /** @var \Drupal\Core\Render\Markup $message */
        foreach ($messages['status'] as $message) {
          $errorMsg .= '<div>' . $message->jsonSerialize() . '</div>';
        }
      }
      if (!empty($errorMsg)) {
        $errorMsg = "<div style='text-align: left;'>$errorMsg</div>";
      }
      if (!empty($file[0])) {
        $origFileName = $file[0]->getFilename();
        $alt  = pathinfo($origFileName, PATHINFO_FILENAME);
        $alt  = str_replace('_', ' ', $alt);
        $uri  = $file[0]->getFileUri();
        $uuid = $file[0]->uuid();
        $fileName = $fileSystem->basename($uri);
        $url = file_url_transform_relative(file_create_url($uri));
        $entityType = $file[0]->getEntityTypeId();
        $moduleHandler = \Drupal::service('module_handler');
        if ($moduleHandler->moduleExists('inline_responsive_images')){
          // Get a responsive image style.
          $responsiveImage = $editor->getFilterFormat()->filters('filter_responsive_image_style');
          $responsiveImageSettings = $responsiveImage->getConfiguration();
          foreach ($responsiveImageSettings['settings'] as $responsiveImageStyle => $enabled) {
            if ($enabled == '1') {
              // Make the first responsive image style as default.
              $defaultResponsiveImageStyle = str_replace('responsive_style_', '', $responsiveImageStyle);
              break;
            }
          }
        }
        if ($moduleHandler->moduleExists('media')) {
          $mediaImageFields = \Drupal::service('entity_field.manager')->getFieldDefinitions('media', 'image');
          if (isset($mediaImageFields['field_media_image']) && $imageUpload['media_entity_image']) {
            // Create media entity with saved file.
            $imageMedia = \Drupal\media\Entity\Media::create([
              'bundle' => 'image',
              'name' => $alt,
              'uid' => \Drupal::currentUser()->id(),
              'langcode' => \Drupal::languageManager()->getDefaultLanguage()->getId(),
              'status' => \Drupal\node\NodeInterface::PUBLISHED,
              'thumbnail' => [
                'target_id' => $file[0]->id(),
              ],
              'field_media_image' => [
                0 => [
                  'target_id' => $file[0]->id(),
                  'alt' => $alt,
                ],
              ],
            ]);
            $imageMedia->save();
          }
        }
      }
      else {
        $status  = FALSE;
      }
    }
    if (!$status) {
      $json = [
        'uploaded' => $status,
        'error' => [
          'message' => $errorMsg,
        ],
      ];
    }
    else {
      $json = [
        'uploaded' => $status,
        'fileName' => $fileName,
        'url' => $url,
        'alt' => $alt,
        'entityUuid' => $uuid,
        'entityType' => $entityType,
        'responsiveImageStyle' => $defaultResponsiveImageStyle,
        'error' => [
          'message' => $errorMsg,
        ],
      ];
    }

    return new JsonResponse($json);
  }
}
