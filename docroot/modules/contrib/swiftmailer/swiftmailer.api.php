<?php

/**
 * @file
 * Hooks specific to the SwiftMailer module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter messages before sending it with SwiftMailer.
 *
 * @see \Drupal\swiftmailer\Plugin\Mail\SwiftMailer::mail
 */
function hook_swiftmailer_alter(Swift_Mailer &$swiftMailer, Swift_Message &$swiftMessage, $message) {
  // Set read receipt.
  $swiftMessage->setReadReceiptTo(['your@address.com']);

  // Register a SwiftMailer Plugin.
  // @see https://swiftmailer.symfony.com/docs/plugins.html
  $replacements = [];
  $decorator = new Swift_Plugins_DecoratorPlugin($replacements);
  $swiftMailer->registerPlugin($decorator);
}

/**
 * @} End of "addtogroup hooks".
 */
