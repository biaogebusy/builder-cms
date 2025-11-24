<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin\Field\FieldWidget;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\telephone\Plugin\Field\FieldWidget\TelephoneDefaultWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\sms\Exception\PhoneNumberSettingsException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'sms_telephone' widget.
 *
 * @FieldWidget(
 *   id = \Drupal\sms\Plugin\Field\FieldWidget\SmsTelephoneWidget::PLUGIN_ID,
 *   label = @Translation("SMS Framework Telephone"),
 *   field_types = {
 *     "telephone"
 *   }
 * )
 */
class SmsTelephoneWidget extends TelephoneDefaultWidget {

  public const PLUGIN_ID = 'sms_telephone';

  /**
   * Constructs a SmsTelephoneWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, protected TimeInterface $time) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    /** @var \Drupal\sms\Provider\PhoneNumberVerificationInterface $phone_number_verification_provider */
    $phone_number_verification_provider = \Drupal::service('sms.phone_number.verification');
    try {
      $config = $phone_number_verification_provider->getPhoneNumberSettingsForEntity($items->getEntity());
    }
    catch (PhoneNumberSettingsException $e) {
      return $element;
    }

    /** @var \Drupal\Core\Datetime\DateFormatter $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    $current_time = $this->time->getRequestTime();

    $t_args['@url'] = Url::fromRoute('sms.phone.verify')->toString();
    $lifetime = $config->getVerificationCodeLifetime() ?: 0;

    if (isset($items[$delta]->value)) {
      $phone_verification = $phone_number_verification_provider
        ->getPhoneVerificationByEntity($items->getEntity(), $items[$delta]->value);

      if ($phone_verification) {
        if ($phone_verification->getStatus()) {
          $element['value']['#description'] = $this->t('This phone number is verified. <strong>Warning:</strong> Modifying this phone number will remove verification.');
        }
        else {
          $element['value']['#disabled'] = TRUE;
          $expiration_date = $phone_verification->getCreatedTime() + $lifetime;

          if ($current_time < $expiration_date) {
            $t_args['@time'] = $date_formatter->formatTimeDiffUntil($expiration_date, [
              'granularity' => 2,
            ]);
            $element['value']['#description'] = $this->t('A verification code has been sent to this phone number. Go to the <a href="@url">verification form</a> and enter the code. The code will expire if it is not verified in @time.', $t_args);
          }
          else {
            // This message displays if we are waiting for cron to delete
            // expired verification codes.
            $element['value']['#description'] = $this->t('Verification code expired. Try again later.');
          }
        }
      }
      else {
        // This message will display if there is a field value, but the
        // verification expired.
        $t_args['@time'] = $date_formatter->formatInterval($lifetime, 2);
        $element['value']['#description'] = $this->t('Save this form to send a new verification code as an SMS message, you must enter the code into the <a href="@url">verification form</a> within @time.', $t_args);
      }
    }
    else {
      $t_args['@time'] = $date_formatter->formatInterval($lifetime, 2);
      $element['value']['#description'] = $this->t('Enter a phone number. A verification code will be sent as an SMS message, you must enter the code into the <a href="@url">verification form</a> within @time.', $t_args);
    }

    return $element;
  }

}
