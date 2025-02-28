<?php

namespace Drupal\profile_switcher;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Switches the site's install profile.
 *
 * WARNING: This can potentially render a site unstable!
 */
class ProfileSwitcher {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Key Value Factory service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyvalue;

  /**
   * The State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Creates a ProfileSwitcher instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyvalue
   *   The Key Value Factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    KeyValueFactoryInterface $keyvalue,
    StateInterface $state
  ) {
    $this->configFactory = $config_factory;
    $this->keyvalue = $keyvalue;
    $this->state = $state;
  }

  /**
   * Switches the site's install profile.
   *
   * Note:
   * - This does not check that currently enabled modules will still be
   *   accessible from the new profile.
   * - This does not run the new profile's install hooks.
   *
   * @param string $profile_to_install
   *   The machine name of the profile to switch to.
   */
  public function switchProfile($profile_to_install) {
    $profile_to_remove = \Drupal::installProfile();

    // Forces ExtensionDiscovery to rerun for profiles.
    $this->state->delete('system.profile.files');

    // Set the profile in configuration.
    $extension_config = $this->configFactory->getEditable('core.extension');
    $extension_config->set('profile', $profile_to_install)
      ->save();

    drupal_flush_all_caches();

    // Install profiles are also registered as enabled modules.
    // Remove the old profile and add in the new one.
    $extension_config->clear("module.{$profile_to_remove}")
      ->save();
    // The install profile is always given a weight of 1000 by the core
    // extension system.
    $extension_config->set("module.$profile_to_install", 1000)
      ->save();

    // Remove the schema value for the old install profile, and set the schema
    // for the new one. We set the schema version to 8000, in the absence of any
    // knowledge about it. TODO: add an option for the schema version to set for
    // the new profile, or better yet, analyse the profile's hook_update_N()
    // functions to deduce the schema to set.
    $this->keyvalue->get('system.schema')->delete($profile_to_remove);
    $this->keyvalue->get('system.schema')->set($profile_to_install, 8000);

    // Clear caches again.
    drupal_flush_all_caches();
  }

}
