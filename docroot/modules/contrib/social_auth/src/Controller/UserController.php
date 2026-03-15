<?php

namespace Drupal\social_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Drupal\views\Views;

/**
 * Defines a class to show social_auth entities for user.
 */
class UserController extends ControllerBase {

  /**
   * Social Auth profiles routing callback.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for which the social_auth profiles list is made.
   *
   * @return array
   *   The render array of the user social_auth profiles.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function socialAuthProfiles(UserInterface $user) {

    $build = [];

    /** @var \Drupal\views\ViewEntityInterface $view */
    $view = NULL;
    try {
      $view = $this->entityTypeManager()->getStorage('view')->load('social_auth_profiles');
    }
    catch (\Exception $ignored) {
    }
    if ($view && $view->status()) {
      $view = Views::getView('social_auth_profiles');
      $view
        ->setArguments([
          'user_id' => $user->id(),
        ]);
      $build['list'] = $view->render('default');
    }
    else {
      $build['list'] = $this->entityTypeManager()->getListBuilder('social_auth')->renderForUser($user);
    }

    return $build;
  }

}
