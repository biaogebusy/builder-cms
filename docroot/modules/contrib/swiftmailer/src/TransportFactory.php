<?php

namespace Drupal\swiftmailer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Swift_FileSpool;
use Swift_NullTransport;
use Swift_SendmailTransport;
use Swift_SmtpTransport;
use Swift_SpoolTransport;

/**
 * A service that instantiates transport subsystems.
 */
class TransportFactory implements TransportFactoryInterface {

  /**
   * The transport configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $transportConfig;

  /**
   * Constructs a TransportFactory object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->transportConfig = $config_factory->get('swiftmailer.transport');
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultTransportMethod() {
    return $this->transportConfig->get('transport');
  }

  /**
   * {@inheritdoc}
   */
  public function getTransport($transport_type) {
    // Configure the mailer based on the configured transport type.
    switch ($transport_type) {
      case SWIFTMAILER_TRANSPORT_SMTP:
        // Get transport configuration.
        $host = $this->transportConfig->get('smtp_host');
        $port = $this->transportConfig->get('smtp_port');
        $encryption = $this->transportConfig->get('smtp_encryption');
        $provider = $this->transportConfig->get('smtp_credential_provider');
        $username = NULL;
        $password = NULL;
        if ($provider === 'swiftmailer') {
          $username = $this->transportConfig->get('smtp_credentials')['swiftmailer']['username'];
          $password = $this->transportConfig->get('smtp_credentials')['swiftmailer']['password'];
        }
        elseif ($provider === 'key') {
          /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
          $storage = \Drupal::entityTypeManager()->getStorage('key');
          /** @var \Drupal\key\KeyInterface $username_key */
          $username_key = $storage->load($this->transportConfig->get('smtp_credentials')['key']['username']);
          if ($username_key) {
            $username = $username_key->getKeyValue();
          }
          /** @var \Drupal\key\KeyInterface $password_key */
          $password_key = $storage->load($this->transportConfig->get('smtp_credentials')['key']['password']);
          if ($password_key) {
            $password = $password_key->getKeyValue();
          }
        }
        elseif ($provider === 'multikey') {
          /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
          $storage = \Drupal::entityTypeManager()->getStorage('key');
          /** @var \Drupal\key\KeyInterface $username_key */
          $user_password_key = $storage->load($this->transportConfig->get('smtp_credentials')['multikey']['user_password']);
          if ($user_password_key) {
            $values = $user_password_key->getKeyValues();
            $username = $values['username'];
            $password = $values['password'];
          }
        }

        // Instantiate transport.
        $transport = new Swift_SmtpTransport($host, $port);
        $transport->setLocalDomain('[127.0.0.1]');

        // Set encryption (if any).
        if (!empty($encryption)) {
          $transport->setEncryption($encryption);
        }

        // Set username (if any).
        if (!empty($username)) {
          $transport->setUsername($username);
        }

        // Set password (if any).
        if (!empty($password)) {
          $transport->setPassword($password);
        }
        break;

      case SWIFTMAILER_TRANSPORT_SENDMAIL:
        // Get transport configuration.
        $path = $this->transportConfig->get('sendmail_path');
        $mode = $this->transportConfig->get('sendmail_mode');

        // Instantiate transport.
        $transport = new Swift_SendmailTransport($path . ' -' . $mode);
        break;

      case SWIFTMAILER_TRANSPORT_SPOOL:
        // Instantiate transport.
        $spooldir = $this->transportConfig->get('spool_directory');
        $spool = new Swift_FileSpool($spooldir);
        $transport = new Swift_SpoolTransport($spool);
        break;

      case SWIFTMAILER_TRANSPORT_NULL:
        $transport = new Swift_NullTransport();
        break;
    }

    if (!isset($transport)) {
      throw new \LogicException('The transport method is undefined.');
    }
    return $transport;
  }

}
