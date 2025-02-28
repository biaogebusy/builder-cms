<?php

/**
 * @file
 * Provides token support for profiles.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\profile\Entity\ProfileType;
use Drupal\user\UserInterface;

/**
 * Implements hook_token_info_alter().
 */
function profile_token_info_alter(&$info) {
  // Register a token for each profile type on the user object.
  foreach (ProfileType::loadMultiple() as $profile_type) {
    $args = ['@type' => $profile_type->get('label')];
    $token_name = $profile_type->get('id');
    $info['tokens']['user'][$token_name] = [
      'name' => t('@type profile', $args),
      'description' => t('Profile of the type "@type" associate with a user.', $args),
      'type' => 'profile',
    ];
  }

}

/**
 * Implements hook_tokens().
 */
function profile_tokens($type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];
  // Entity tokens on the Drupal user object.
  if ($type == 'entity' && !empty($data['entity_type']) && !empty($data['entity']) && !empty($data['token_type'])) {
    $user = $data['entity'];
    if (!($user instanceof UserInterface)) {
      return $replacements;
    }

    foreach ($tokens as $name => $original) {
      // Split the token name to see if it is a profile type.
      $parts = explode(':', $name);

      // Determine if this token represents a profile type.
      if (!empty($parts[0]) && in_array($parts[0], array_keys(ProfileType::loadMultiple()))) {
        $profile_type = $parts[0];

        /** @var \Drupal\profile\ProfileStorageInterface $storage */
        $storage = \Drupal::entityTypeManager()->getStorage('profile');
        $profile = $storage->loadByUser($user, $profile_type);

        if (!empty($profile)) {
          // If the token contains prefixes, invoke the field token generators.
          if (count($parts) > 1 && $profile_tokens = \Drupal::token()->findWithPrefix($tokens, $profile_type)) {
            $profile_token_data = [
              'entity' => $profile,
              'entity_type' => 'profile',
              'token_type' => 'profile',
            ];

            // Invoke the generators to resolve the profile token.
            $replacements += \Drupal::token()
              ->generate('entity', $profile_tokens, $profile_token_data, $options, $bubbleable_metadata);
          }
          elseif (count($parts) == 1) {
            // Render the full profile.
            $entity_type_manager = \Drupal::entityTypeManager();
            $view_builder = $entity_type_manager->getViewBuilder('profile');
            $profile_view = $view_builder->view($profile, 'token');

            /** @var Drupal\Core\Render\Renderer $renderer */
            $renderer = \Drupal::service('renderer');
            $replacements[$original] = $renderer->renderRoot($profile_view);
          }
        }
      }
    }
  }

  return $replacements;
}
