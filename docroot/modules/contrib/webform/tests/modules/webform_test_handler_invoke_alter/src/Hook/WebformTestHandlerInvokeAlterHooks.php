<?php

namespace Drupal\webform_test_handler_invoke_alter\Hook;

use Drupal\webform\Plugin\WebformHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_test_handler_invoke_alter.
 */
class WebformTestHandlerInvokeAlterHooks {

  /**
   * Implements hook_webform_handler_invoke_alter().
   */
  #[Hook('webform_handler_invoke_alter')]
  public function webformHandlerInvokeAlter(WebformHandlerInterface $handler, $method_name, array $args) {
    $t_args = [
      '@webform_id' => $handler->getWebform()->id(),
      '@handler_id' => $handler->getHandlerId(),
      '@method_name' => $method_name,
    ];
    \Drupal::messenger()->addStatus(t('Invoking hook_webform_handler_invoke_alter() for "@webform_id:@handler_id::@method_name"', $t_args), TRUE);
  }

}
