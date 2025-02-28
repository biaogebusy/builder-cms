<?php

declare(strict_types = 1);

namespace Drupal\sms_user\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\sms\Provider\PhoneNumberVerificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic menu links for SMS User.
 *
 * @see \Drupal\views\Plugin\Menu\ViewsMenuLink
 */
class SmsUserMenuLink extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The phone number verification service.
   *
   * @var \Drupal\sms\Provider\PhoneNumberVerificationInterface
   */
  protected $phoneNumberVerification;

  /**
   * Constructs a \Drupal\sms_user\Plugin\Derivative\SmsUserMenuLink instance.
   *
   * @param \Drupal\sms\Provider\PhoneNumberVerificationInterface $phone_number_verification
   *   The phone number verification service.
   */
  public function __construct(PhoneNumberVerificationInterface $phone_number_verification) {
    $this->phoneNumberVerification = $phone_number_verification;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('sms.phone_number.verification')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];

    if ($this->phoneNumberVerification->getPhoneNumberSettings('user', 'user')) {
      $links['sms_user_phone_number_settings'] = [
        'title' => t('User phone number'),
        'description' => t('Set up phone number fields and settings for users.'),
        'route_name' => 'entity.phone_number_settings.edit_form',
        'route_parameters' => ['phone_number_settings' => 'user.user'],
        'parent' => 'user.admin_index',
        'weight' => 21,
      ];
    }

    return $links;
  }

}
