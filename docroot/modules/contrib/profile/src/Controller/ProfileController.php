<?php

namespace Drupal\profile\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides the callback for marking a profile as the default one.
 */
class ProfileController extends ControllerBase {

  /**
   * Mark profile as default.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The profile.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect back to the profile listing.
   */
  public function setDefault(ProfileInterface $profile) {
    $profile->setDefault(TRUE);
    $profile->save();
    $this->messenger()->addMessage($this->t('The %label profile has been marked as default.', ['%label' => $profile->label()]));

    return new RedirectResponse($profile->toUrl('collection')->toString());
  }

}
