<?php

namespace Drupal\profile\Event;

/**
 * Defines events for the profile module.
 */
final class ProfileEvents {

  /**
   * Name of the event fired when altering a profile label.
   *
   * @Event
   *
   * @see \Drupal\Profile\Event\ProfileFormatEvent
   */
  const PROFILE_LABEL = 'profile.label';

}
