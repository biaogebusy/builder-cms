<?php

declare(strict_types = 1);

namespace Drupal\sms_blast;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\sms\Provider\PhoneNumberProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sms\Message\SmsMessage;

/**
 * Defines a form for sending mass messages.
 */
class SmsBlastForm extends FormBase {

  /**
   * Storage for Phone Number Verification entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $phoneNumberVerificationStorage;

  /**
   * Phone number provider.
   *
   * @var \Drupal\sms\Provider\PhoneNumberProviderInterface
   */
  protected $phoneNumberProvider;

  /**
   * Constructs a new SmsBlastForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\sms\Provider\PhoneNumberProviderInterface $phone_number_provider
   *   The phone number provider.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PhoneNumberProviderInterface $phone_number_provider, MessengerInterface $messenger) {
    $this->phoneNumberVerificationStorage = $entity_type_manager
      ->getStorage('sms_phone_number_verification');
    $this->phoneNumberProvider = $phone_number_provider;
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('sms.phone_number'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sms_blast_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['message'] = [
      '#type'  => 'textarea',
      '#title' => $this->t('Message'),
      '#cols'  => 60,
      '#rows'  => 5,
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Send'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $sms_message = new SmsMessage();
    $sms_message->setMessage($form_state->getValue('message'));

    $ids = $this->phoneNumberVerificationStorage->getQuery()
      ->condition('status', 1)
      ->condition('entity__target_type', 'user')
      ->execute();

    $success = 0;
    $failure = 0;
    $entity_ids = [];
    /** @var \Drupal\sms\Entity\PhoneNumberVerificationInterface $verification */
    foreach ($this->phoneNumberVerificationStorage->loadMultiple($ids) as $verification) {
      // Ensure entity exists and one message is sent to each entity.
      if (($entity = $verification->getEntity()) && !in_array($entity->id(), $entity_ids)) {
        $entity_ids[] = $entity->id();

        try {
          $this->phoneNumberProvider
            ->sendMessage($entity, $sms_message);
          $success++;
        }
        catch (\Exception $e) {
          $failure++;
        }
      }
    }

    if ($success > 0) {
      $this->messenger()->addMessage($this->formatPlural($success, 'Message sent to @count user.', 'Message sent to @count users.'));
    }
    if ($failure > 0) {
      $this->messenger()->addError($this->formatPlural($failure, 'Message could not be sent to @count user.', 'Message could not be sent to @count users.'));
    }
  }

}
