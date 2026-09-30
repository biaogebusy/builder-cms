<?php

namespace Drupal\xinshi_sms;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Prevents the dependency's legacy OTP service from bypassing shared policy.
 */
final class XinshiSmsServiceProvider extends ServiceProviderBase {

  public function alter(ContainerBuilder $container) {
    $container->setAlias('otp_login.OTP', 'xinshi_sms.OTP')->setPublic(TRUE);
  }

}
