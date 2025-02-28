<?php

namespace Drupal\profile_switcher\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\profile_switcher\ProfileSwitcher;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

/**
 * A Drush commandfile for Profile Switcher module.
 */
class ProfileSwitcherCommands extends DrushCommands {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The profile switcher service.
   *
   * @var \Drupal\profile_switcher\ProfileSwitcher
   */
  protected $profileSwitcher;

  /**
   * Constructs a ProfileSwitcherCommands object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\profile_switcher\ProfileSwitcher $profile_switcher
   *   The profile switcher service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ProfileSwitcher $profile_switcher) {
    $this->configFactory = $config_factory;
    $this->profileSwitcher = $profile_switcher;
  }

  /**
   * Switch the Drupal profile of an installed site.
   *
   * @param string $profile_to_install
   *   The profile to activate.
   *
   * @command switch:profile
   * @aliases sp,switch-profile
   */
  public function profile($profile_to_install) {
    $profile_to_remove = $this->configFactory->get('core.extension')->get('profile');

    $this->output()->writeln(
      dt("The site's install profile will be switched from !profile_to_remove to !profile_to_install.", [
        '!profile_to_remove' => $profile_to_remove,
        '!profile_to_install' => $profile_to_install,
      ])
    );
    if (!$this->io()->confirm(dt('Do you want to continue?'))) {
      throw new UserAbortException();
    }

    $this->profileSwitcher->switchProfile($profile_to_install);

    $this->output()->writeln('Profile changed to: ' . $profile_to_install);
  }

}
