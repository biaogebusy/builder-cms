<?php

declare(strict_types = 1);

namespace Drupal\sms_sendtophone\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\sms\Provider\SmsProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Direction;
use Drupal\user\Entity\User;
use Drupal\sms\Exception\PhoneNumberSettingsException;

/**
 * Default controller for the sms_sendtophone module.
 */
class SendToPhoneForm extends FormBase {

  /**
   * Phone numbers for the authenticated user.
   *
   * @var array
   */
  protected $phoneNumbers = [];

  /**
   * The SMS Provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected $smsProvider;

  /**
   * Creates an new SendForm object.
   *
   * @param \Drupal\sms\Provider\SmsProviderInterface $sms_provider
   *   The SMS service provider.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(SmsProviderInterface $sms_provider, MessengerInterface $messenger) {
    $this->smsProvider = $sms_provider;
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sms.provider'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $type = NULL, $extra = NULL) {
    /** @var \Drupal\sms\Provider\PhoneNumberProviderInterface $phone_number_provider */
    $phone_number_provider = \Drupal::service('sms.phone_number');
    /** @var \Drupal\user\UserInterface $user */
    $user = User::load($this->currentUser()->id());

    // @todo This block should be a route access checker.
    try {
      $this->phoneNumbers = $phone_number_provider->getPhoneNumbers($user);
    }
    catch (PhoneNumberSettingsException $e) {
    }

    if ($user->hasPermission('send to any number') || count($this->phoneNumbers)) {
      $form = $this->getForm($form, $form_state, $type, $extra);
    }
    else {
      if (!count($this->phoneNumbers)) {
        // User has no phone number, or unconfirmed.
        $form['message'] = [
          '#type' => 'markup',
          '#markup' => $this->t('You need to @setup and confirm your mobile phone to send messages.', [
            '@setup' => $user->toLink('set up', 'edit-form')->toString(),
          ]),
        ];
      }
      else {
        $destination = ['query' => \Drupal::service('redirect.destination')->getAsArray()];
        $form['message'] = [
          '#markup' => $this->t('You do not have permission to send messages. You may need to @signin or @register for an account to send messages to a mobile phone.',
            [
              '@signin' => Link::fromTextAndUrl($this->t('sign in'), Url::fromRoute('user.page', [], $destination)),
              '@register' => Link::fromTextAndUrl($this->t('register'), Url::fromRoute('user.register', [], $destination)),
            ]),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sms_sendtophone_form';
  }

  /**
   * Builds the form array.
   */
  protected function getForm(array $form, FormStateInterface $form_state, $type = NULL, $extra = NULL) {
    switch ($type) {
      case 'cck':
      case 'field':
      case 'inline':
        $form['message'] = [
          '#type' => 'value',
          '#value' => $this->getRequest()->get('text'),
        ];
        $form['message_preview'] = [
          '#type' => 'item',
          '#markup' => '<p class="sms-sendtophone--message-preview">' . $this->getRequest()->get('text') . '</p>',
          '#title' => $this->t('Message preview'),
        ];
        break;

      case 'node':
        if (is_numeric($extra)) {
          $node = Node::load($extra);
          $form['message_display'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Message preview'),
            '#description' => $this->t('This URL will be sent to the phone.'),
            '#cols' => 35,
            '#rows' => 2,
            '#attributes' => ['disabled' => TRUE],
            '#default_value' => $node->toUrl()->setAbsolute()->toString(),
          ];
          $form['message'] = [
            '#type' => 'value',
            '#value' => $node->toUrl()->setAbsolute()->toString(),
          ];
        }
        break;
    }

    $form['number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone number'),
    ];

    if (count($this->phoneNumbers)) {
      $form['number']['#default_value'] = reset($this->phoneNumbers);
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#weight' => 20,
    ];

    // Add library for CSS styling.
    $form['#attached']['library'] = 'sms_sendtophone/default';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = User::load($this->currentUser()->id());
    $number = $form_state->getValue('number');
    $message = $form_state->getValue('message');

    $sms_message = SmsMessage::create()
      ->setDirection(Direction::OUTGOING)
      ->setMessage($message)
      ->setSenderEntity($user)
      ->addRecipient($number);

    try {
      $this->smsProvider->queue($sms_message);
      $this->messenger()->addMessage($this->t('Message has been sent.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Message could not be sent: @error', [
        '@error' => $e->getMessage(),
      ]));
    }
  }

}
