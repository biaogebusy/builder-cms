<?php

namespace Drupal\captcha\Constants;

/**
 * Constants for the captcha module.
 */
class CaptchaConstants {
  // CAPTCHA CONSTANTS:
  // Always add a CAPTCHA (even on every page of a multipage workflow).
  const CAPTCHA_PERSISTENCE_SHOW_ALWAYS = 0;
  // Only one CAPTCHA has to be solved per form instance/multi-step workflow.
  const CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE = 1;
  // Once the user answered correctly for a CAPTCHA on a certain form type,
  // no more CAPTCHAs will be offered anymore for that form.
  const CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_TYPE = 2;
  // Once the user answered correctly for a CAPTCHA on the site,
  // no more CAPTCHAs will be offered anymore.
  const CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL = 3;

  const CAPTCHA_STATUS_UNSOLVED = 0;
  const CAPTCHA_STATUS_SOLVED = 1;
  const CAPTCHA_STATUS_EXAMPLE = 2;

  const CAPTCHA_DEFAULT_VALIDATION_CASE_SENSITIVE = 0;
  const CAPTCHA_DEFAULT_VALIDATION_CASE_INSENSITIVE = 1;

  const CAPTCHA_WHITELIST_IP_ADDRESS = 'addresses';
  const CAPTCHA_WHITELIST_IP_RANGE = 'ranges';

  /**
   * Default captcha field access.
   */
  const CAPTCHA_FIELD_DEFAULT_ACCESS = 1;

  /**
   * The math captcha type.
   */
  const CAPTCHA_MATH_CAPTCHA_TYPE = 'captcha/Math';

  /**
   * The default captcha type.
   */
  const CAPTCHA_TYPE_DEFAULT = 'default';

}
