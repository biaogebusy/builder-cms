<?php

/**
 * @file
 * Provides tokens for the Private Message module.
 */

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Implements hook_token_info().
 */
function private_message_token_info() {

  // Defined the private message token type.
  $private_message_type = [
    'name' => t('Private Messages'),
    'description' => t('Tokens related to private messages'),
    'needs-data' => 'private_message',
  ];

  // Define the private message thread token type.
  $private_message_thread_type = [
    'name' => t('Private Message Threads'),
    'description' => t('Tokens related to private message threads'),
    'needs-data' => 'private_message_thread',
  ];

  // Define private message tokens.
  $private_message_tokens['id'] = [
    'name' => t('ID'),
    'description' => t('The ID of the private message'),
  ];

  $private_message_tokens['message'] = [
    'name' => t('Message'),
    'description' => t('The raw body of the private message'),
  ];

  $private_message_tokens['author-name'] = [
    'name' => t('Author'),
    'description' => t('The author of the private message'),
  ];

  $private_message_tokens['url'] = [
    'name' => t('Url'),
    'description' => t('The URL of the individual private message. Use the URL token of the private message thread to link to the conversation'),
  ];

  $private_message_tokens['created'] = [
    'name' => t('Creation date'),
    'description' => t('The date at which the message was posted'),
    'type' => 'date',
  ];

  // Define private message thread tokens.
  $private_message_thread_tokens['id'] = [
    'name' => t('ID'),
    'description' => t('The ID of the private message thread'),
  ];

  $private_message_thread_tokens['url'] = [
    'name' => t('URL'),
    'description' => t('The link to the private message thread'),
  ];

  $private_message_thread_tokens['updated'] = [
    'name' => t('Last update'),
    'description' => t('The date at which the thread was last updated'),
    'type' => 'date',
  ];

  return [
    'types' => [
      'private_message' => $private_message_type,
      'private_message_thread' => $private_message_thread_type,
    ],
    'tokens' => [
      'private_message' => $private_message_tokens,
      'private_message_thread' => $private_message_thread_tokens,
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function private_message_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  $token_service = \Drupal::token();

  $url_options = ['absolute' => TRUE];
  if (isset($options['langcode'])) {
    $url_options['language'] = \Drupal::languageManager()->getLanguage($options['langcode']);
    $langcode = $options['langcode'];
  }
  else {
    $langcode = LanguageInterface::LANGCODE_DEFAULT;
  }

  // Replace private message tokens.
  if ($type == 'private_message' && !empty($data['private_message'])) {


    /** @var \Drupal\private_message\Entity\PrivateMessageInterface $private_message */
    $private_message = $data['private_message'];

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'id':
          $replacements[$original] = $private_message->id();
          break;

        case 'message':
          $replacements[$original] = $private_message->getMessage();
          break;

        case 'author-name':
          $account = $private_message->getOwner() ? $private_message->getOwner() : User::load(0);
          $bubbleable_metadata->addCacheableDependency($account);
          $replacements[$original] = $account->label();
          break;

        case 'url':
          $replacements[$original] = $private_message->toUrl('canonical', $url_options);

        case 'created':
          $date_format = DateFormat::load('medium');
          $bubbleable_metadata->addCacheableDependency($date_format);
          $replacements[$original] = \Drupal::service('date.formatter')->format($private_message->getCreatedTime(), 'medium', '', NULL, $langcode);
          break;
      }
    }

    if ($created_tokens = $token_service->findWithPrefix($tokens, 'created')) {
      $replacements += $token_service->generate('date', $created_tokens, ['date' => $private_message->getCreatedTime()], $options, $bubbleable_metadata);
    }
  }
  // Replace private message thread tokens.
  elseif ($type == 'private_message_thread' && !empty($data['private_message_thread'])) {

    $private_message_thread = $data['private_message_thread'];

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'id':
          $replacements[$original] = $private_message_thread->id();
          break;

        case 'url':
          $replacements[$original] = Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $private_message_thread->id()], $url_options)->toString();
          break;

        case 'updated':
          $date_format = DateFormat::load('medium');
          $bubbleable_metadata->addCacheableDependency($date_format);
          $replacements[$original] = \Drupal::service('date.formatter')->format($private_message_thread->getUpdatedTime(), 'medium', '', NULL, $langcode);
          break;
      }
    }

    if ($updated_tokens = $token_service->findWithPrefix($tokens, 'updated')) {
      $replacements += $token_service->generate('date', $updated_tokens, ['date' => $private_message_thread->getUpdatedTime()], $options, $bubbleable_metadata);
    }
  }

  return $replacements;
}
