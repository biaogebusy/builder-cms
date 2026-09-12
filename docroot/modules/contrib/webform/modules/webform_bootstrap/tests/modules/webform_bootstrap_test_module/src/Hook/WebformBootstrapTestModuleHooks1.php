<?php

namespace Drupal\webform_bootstrap_test_module\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_bootstrap_test_module.
 */
class WebformBootstrapTestModuleHooks1 {

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments) {
    $attachments['#attached']['library'][] = 'webform_bootstrap_test_module/webform_bootstrap_test_module';
  }

}
