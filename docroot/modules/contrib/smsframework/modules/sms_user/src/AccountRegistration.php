<?php

declare(strict_types = 1);

namespace Drupal\sms_user;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Utility\Token;
use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\sms\Provider\PhoneNumberVerificationInterface;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Direction;
use Drupal\user\Entity\User;
use Drupal\Component\Utility\Random;
use Drupal\sms\Entity\SmsMessage;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Drupal\user\UserInterface;

/**
 * Defines the account registration service.
 */
class AccountRegistration implements AccountRegistrationInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The SMS provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected $smsProvider;

  /**
   * Phone number verification provider.
   *
   * @var \Drupal\sms\Provider\PhoneNumberVerificationInterface
   */
  protected $phoneNumberVerificationProvider;

  /**
   * Phone number settings for user.user bundle.
   *
   * @var \Drupal\sms\Entity\PhoneNumberSettingsInterface
   */
  protected $userPhoneNumberSettings;

  /**
   * Constructs a AccountRegistration object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Utility\Token $token
   *   The token replacement system.
   * @param \Drupal\sms\Provider\SmsProviderInterface $sms_provider
   *   The SMS provider.
   * @param \Drupal\sms\Provider\PhoneNumberVerificationInterface $phone_number_verification_provider
   *   The phone number verification provider.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Token $token, SmsProviderInterface $sms_provider, PhoneNumberVerificationInterface $phone_number_verification_provider) {
    $this->configFactory = $config_factory;
    $this->token = $token;
    $this->smsProvider = $sms_provider;
    $this->phoneNumberVerificationProvider = $phone_number_verification_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function createAccount(SmsMessageInterface $sms_message) {
    $this->userPhoneNumberSettings = $this->phoneNumberVerificationProvider
      ->getPhoneNumberSettings('user', 'user');
    if (!$this->userPhoneNumberSettings) {
      // Can't do anything if there is no phone number settings for user.
      return;
    }

    $sender_number = $sms_message->getSenderNumber();
    if (!empty($sender_number)) {
      // Any users with this phone number?
      $entities = $this->phoneNumberVerificationProvider
        ->getPhoneVerificationByPhoneNumber($sender_number, NULL, 'user');
      if (!count($entities)) {
        if (!empty($this->settings('unrecognized_sender.status'))) {
          $this->allUnknownNumbers($sms_message);
        }
        if (!empty($this->settings('incoming_pattern.status'))) {
          $this->incomingPatternMessage($sms_message);
        }
      }
    }
  }

  /**
   * Process incoming message and create a user if the phone number is unknown.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms_message
   *   An incoming SMS message.
   */
  protected function allUnknownNumbers(SmsMessageInterface $sms_message) {
    $user = User::create(['name' => $this->generateUniqueUsername()]);
    $user->activate();

    // Sender phone number.
    $sender_number = $sms_message->getSenderNumber();
    $t_args['%sender_phone_number'] = $sender_number;
    $phone_field_name = $this->userPhoneNumberSettings
      ->getFieldName('phone_number');
    $user->{$phone_field_name}[] = $sender_number;

    // Password.
    /** @var \Drupal\Core\Password\PasswordGeneratorInterface $passwordGenerator */
    $passwordGenerator = \Drupal::service('password_generator');
    $password = $passwordGenerator->generate();
    $user->setPassword($password);

    $validate = $this->removeAcceptableViolations($user->validate());
    if ($validate->count() == 0) {
      $user->save();

      // @todo autoconfirm the number?
      // @see https://www.drupal.org/node/2709911
      $t_args['%name'] = $user->label();
      $t_args['%uid'] = $user->id();
      \Drupal::logger('sms_user.account_registration.unrecognized_sender')
        ->info('Creating new account for %sender_phone_number. Username: %name. User ID: %uid', $t_args);

      // Optionally send a reply.
      if (!empty($this->settings('unrecognized_sender.reply.status'))) {
        $message = $this->settings('unrecognized_sender.reply.message');
        $message = str_replace('[user:password]', $password, $message);
        $this->sendReply($sender_number, $user, $message);
      }
    }
    else {
      $t_args['@error'] = $this->buildError($validate);
      \Drupal::logger('sms_user.account_registration.unrecognized_sender')
        ->error('Could not create new account for %sender_phone_number because there was a problem with validation: @error', $t_args);
    }
  }

  /**
   * Creates a user if an incoming message contents matches a pattern.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms_message
   *   An incoming SMS message.
   */
  protected function incomingPatternMessage(SmsMessageInterface $sms_message) {
    if (!empty($this->settings('incoming_pattern.incoming_messages.0'))) {
      $incoming_form = $this->settings('incoming_pattern.incoming_messages.0');
      $incoming_form = str_replace("\r\n", "\n", $incoming_form);
      $compiled = $this->compileFormRegex($incoming_form, '/');
      $matches = [];
      if (preg_match_all('/^' . $compiled . '$/', $sms_message->getMessage(), $matches)) {
        $contains_email = strpos($incoming_form, '[email]') !== FALSE;
        $contains_username = strpos($incoming_form, '[username]') !== FALSE;
        $contains_password = strpos($incoming_form, '[password]') !== FALSE;

        $username = (!empty($matches['username'][0]) && $contains_username) ? $matches['username'][0] : $this->generateUniqueUsername();
        $user = User::create(['name' => $username]);
        $user->activate();

        // Sender phone number.
        $sender_number = $sms_message->getSenderNumber();
        $t_args['%sender_phone_number'] = $sender_number;

        // Sender phone number.
        $phone_field_name = $this->userPhoneNumberSettings
          ->getFieldName('phone_number');
        $user->{$phone_field_name}[] = $sender_number;

        if (!empty($matches['email'][0]) && $contains_email) {
          $user->setEmail($matches['email'][0]);
        }

        /** @var \Drupal\Core\Password\PasswordGeneratorInterface $passwordGenerator */
        $passwordGenerator = \Drupal::service('password_generator');
        $password = (!empty($matches['password'][0]) && $contains_password) ? $matches['password'][0] : $passwordGenerator->generate();
        $user->setPassword($password);

        $validate = $this->removeAcceptableViolations($user->validate(), $incoming_form);
        if ($validate->count() == 0) {
          $user->save();

          // @todo autoconfirm the number?
          // @see https://www.drupal.org/node/2709911
          $message = $this->settings('incoming_pattern.reply.message');
          $message = str_replace('[user:password]', $password, $message);

          \Drupal::logger('sms_user.account_registration.incoming_pattern')
            ->info('Creating new account for %sender_phone_number. Username: %name. User ID: %uid', $t_args + [
              '%uid' => $user->id(),
              '%name' => $user->label(),
            ]);

          // Send an activation email if no password placeholder is found.
          if (!$contains_password && !empty($this->settings('incoming_pattern.send_activation_email'))) {
            _user_mail_notify('register_no_approval_required', $user);
          }
        }
        else {
          $message = $this->settings('incoming_pattern.reply.message_failure');

          $error = $this->buildError($validate);
          $message = str_replace('[error]', $error, $message);

          \Drupal::logger('sms_user.account_registration.incoming_pattern')
            ->warning('Could not create new account for %sender_phone_number because there was a problem with validation: @error', $t_args + [
              '@error' => $error,
            ]);
        }

        // Optionally send a reply.
        if (!empty($this->settings('incoming_pattern.reply.status'))) {
          $this->sendReply($sender_number, $user, $message);
        }
      }
    }
  }

  /**
   * Send a reply message to the sender of a message.
   *
   * @param string $sender_number
   *   Phone number of sender of incoming message. And if a user was created,
   *   this number was used.
   * @param \Drupal\user\UserInterface $user
   *   A user account. The account may not be saved.
   * @param string $message
   *   Message to send as a reply.
   */
  protected function sendReply($sender_number, UserInterface $user, $message) {
    $sms_message = SmsMessage::create();
    $sms_message
      ->addRecipient($sender_number)
      ->setDirection(Direction::OUTGOING);

    $data['sms-message'] = $sms_message;
    $data['user'] = $user;
    $sms_message->setMessage($this->token->replace($message, $data));

    // Use queue(), instead of phone number provider sendMessage()
    // because the phone number is not confirmed.
    try {
      $this->smsProvider->queue($sms_message);
    }
    catch (\Exception $e) {
      $t_args['%recipient'] = $sender_number;
      $t_args['%error'] = $e->getMessage();
      \Drupal::logger('sms_user.account_registration.incoming_pattern')
        ->warning('Reply message could not be sent to recipient %recipient: %error', $t_args);
    }
  }

  /**
   * Compile incoming form configuration to a regular expression.
   *
   * @param string $form_string
   *   A incoming form configuration message.
   * @param string $delimiter
   *   The delimiter to escape, as used by preg_match*().
   *
   * @return string
   *   A regular expression.
   */
  protected function compileFormRegex($form_string, $delimiter) {
    $placeholders = ['username' => '.+', 'email' => '\S+', 'password' => '.+'];

    // Placeholders enclosed in square brackets and escaped for use in regular
    // expressions. \[user\] , \[email\] etc.
    $regex_placeholders = [];
    foreach (array_keys($placeholders) as $d) {
      $regex_placeholders[] = preg_quote('[' . $d . ']');
    }

    // Split message so placeholders are separated from other text.
    // e.g. for 'U [username] P [password], splits to:
    // 'U ', '[username]', ' P ', '[password]'.
    $regex = '/(' . implode('|', $regex_placeholders) . '+)/';
    $words = preg_split($regex, $form_string, -1, PREG_SPLIT_DELIM_CAPTURE);

    // Track if a placeholder was used, so subsequent usages create a named back
    // reference. This allows you to use placeholders more than once as a form
    // of confirmation. e.g: 'U [username] P [password] [password]'.
    $placeholder_usage = [];

    $compiled = '';
    foreach ($words as $word) {
      // Remove square brackets from word to determine if it is a placeholder.
      $placeholder = mb_substr($word, 1, -1);

      // Determine if word is a placeholder.
      if (isset($placeholders[$placeholder])) {
        $placeholder_regex = $placeholders[$placeholder];
        if (!in_array($placeholder, $placeholder_usage)) {
          // Convert placeholder to a capture group.
          $compiled .= '(?<' . $placeholder . '>' . $placeholder_regex . ')';
          $placeholder_usage[] = $placeholder;
        }
        else {
          // Create a back reference to the previous named capture group.
          $compiled .= '\k{' . $placeholder . '}';
        }
      }
      else {
        // Text is not a placeholder, do not convert to a capture group.
        $compiled .= preg_quote($word, $delimiter);
      }
    }

    return $compiled;
  }

  /**
   * Build an error string from a constraint violation list.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   A constraint violation list.
   *
   * @return string
   *   Violation errors joined together.
   */
  protected function buildError(ConstraintViolationListInterface $violations) {
    $error = '';
    foreach ($violations as $violation) {
      $error .= (string) $violation->getMessage() . " ";
    }
    return strip_tags($error);
  }

  /**
   * Generate a unique user name that is not being used.
   *
   * @return string
   *   A unique user name.
   */
  protected function generateUniqueUsername() {
    $random = new Random();
    do {
      $username = $random->name(8, TRUE);
    } while (user_validate_name($username) || user_load_by_name($username));
    return $username;
  }

  /**
   * Filter out acceptable validation errors.
   *
   * @param \Drupal\Core\Entity\EntityConstraintViolationListInterface $violations
   *   A violation list.
   * @param string|null $incoming_form
   *   Incoming form, if applicable.
   *
   * @return \Drupal\Core\Entity\EntityConstraintViolationListInterface
   *   A filtered violation list.
   */
  protected function removeAcceptableViolations(EntityConstraintViolationListInterface $violations, $incoming_form = NULL) {
    // 'mail' will not fail validation if current user has 'administer users'.
    $needs_email = isset($incoming_form) && (strpos($incoming_form, '[email]') !== FALSE);
    if (!$needs_email) {
      // Invalid email field is acceptable if it is not required.
      foreach ($violations as $offset => $violation) {
        if ($violation->getPropertyPath() == 'mail') {
          $violations->remove($offset);
        }
      }
    }

    return $violations;
  }

  /**
   * Get the account_registration configuration.
   *
   * @param string $name
   *   The configuration name.
   *
   * @return array|null
   *   The values for the requested configuration.
   */
  protected function settings($name) {
    return $this->configFactory
      ->get('sms_user.settings')
      ->get('account_registration.' . $name);
  }

}
