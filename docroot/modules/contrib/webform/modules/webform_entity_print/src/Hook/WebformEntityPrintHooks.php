<?php

namespace Drupal\webform_entity_print\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_entity_print.
 */
class WebformEntityPrintHooks {
    // phpcs:ignore

  /**
   * Implements hook_webform_submission_access().
   */
  #[Hook('webform_submission_access')]
  public function webformSubmissionAccess(WebformSubmissionInterface $webform_submission, $operation, AccountInterface $account) {
    if ($operation !== 'view') {
      return AccessResult::neutral();
    }
    // Only override access controls when displaying images.
    $route_name = \Drupal::routeMatch()->getRouteName();
    if (!in_array($route_name, ['system.files', 'image.style_private'])) {
      return AccessResult::neutral();
    }
    // Make sure the webform entity print token is defined.
    $webform_entity_print_token = \Drupal::request()->query->get(WEBFORM_ENTITY_PRINT_IMAGE_TOKEN);
    if (!$webform_entity_print_token) {
      return AccessResult::neutral();
    }
    // Make sure the URI contains /{webform}/{sid}/.
    $uri = \Drupal::request()->getUri();
    $webform_id = $webform_submission->getWebform()->id();
    $sid = $webform_submission->id();
    if (!preg_match('#(?:/private|/system/files)/webform/' . $webform_id . '/' . $sid . '#', parse_url($uri, PHP_URL_PATH))) {
      return AccessResult::neutral();
    }
    $encrypt_token = _webform_entity_print_token_generate($uri);
    return AccessResult::allowedIf($webform_entity_print_token === $encrypt_token);
  }

  /**
   * Implements hook_file_download().
   */
  #[Hook('file_download')]
  public function fileDownload($uri) {
    if (!preg_match('#/webform/([^/]+)/#', $uri, $match)) {
      return NULL;
    }
    $webform_id = $match[1];
    $webform = Webform::load($webform_id);
    if (!$webform) {
      return NULL;
    }
    // Get signature elements.
    $signature_elements = [];
    $elements = $webform->getElementsDecodedAndFlattened();
    foreach ($elements as $element_key => $element) {
      if (isset($element['#type']) && $element['#type'] === 'webform_signature') {
        $signature_elements[] = $element_key;
      }
    }
    // Match signature element.
    if ($signature_elements && preg_match_all('#/webform/' . $webform_id . '/(' . implode('|', $signature_elements) . ')/#', $uri, $matches)) {
      $webform_entity_print_token = \Drupal::request()->get('webform_entity_print_itok');
      $encrypt_token = _webform_entity_print_token_generate(\Drupal::service('file_url_generator')->generateAbsoluteString($uri));
      if ($webform_entity_print_token === $encrypt_token) {
        /** @var \Drupal\Core\File\FileSystemInterface $file_system */
        $file_system = \Drupal::service('file_system');
        $filename = \basename($uri);
        $filesize = filesize($file_system->realpath($uri));
        return [
          'Content-Type' => 'image/png',
          'Content-Length' => $filesize,
          'Cache-Control' => 'private',
          'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, (string) $filename),
        ];
      }
    }
    return NULL;
  }

}
