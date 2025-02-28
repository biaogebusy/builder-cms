<?php

namespace Drupal\views_add_button\Plugin\views_add_button;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Url;
use Drupal\views_add_button\ViewsAddButtonInterface;

/**
 * User plugin for Views Add Button.
 *
 * @ViewsAddButton(
 *   id = "views_add_button_user",
 *   label = @Translation("ViewsAddButtonUser"),
 *   target_entity = "user"
 * )
 */
class ViewsAddButtonUser extends PluginBase implements ViewsAddButtonInterface {

  /**
   * Plugin description.
   *
   * @return string
   *   A string description.
   */
  public function description() {
    return $this->t('Views Add Button URL Generator for User entities');
  }

  /**
   * Check for access to the appropriate "add" route.
   *
   * @param string $entity_type
   *   Entity id as a machine name.
   * @param string $bundle
   *   The bundle string.
   * @param string $context
   *   Entity context string
   *
   * @return bool
   *   Whether we have access.
   */
  public static function checkAccess($entity_type, $bundle, $context) {
    $accessManager = \Drupal::service('access_manager');
    return $accessManager->checkNamedRoute('user.admin_create', [], \Drupal::currentUser());
  }

  /**
   * Generate the add button URL.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle ID.
   * @param array $options
   *   Array of options to be passed to the Url object.
   * @param string $context
   *   Module-specific context string.
   *
   * @return \Drupal\Core\Url
   *   Url object which is used to construct the add button link
   */
  public static function generateUrl($entity_type, $bundle, array $options, $context = '') {

    // Create URL from the data above.
    $url = Url::fromRoute('user.admin_create', [], $options);

    return $url;
  }

}
