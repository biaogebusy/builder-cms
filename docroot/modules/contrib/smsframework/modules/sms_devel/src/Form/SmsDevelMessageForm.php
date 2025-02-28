<?php

declare(strict_types = 1);

namespace Drupal\sms_devel\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\sms\Entity\SmsGateway;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Direction;
use Drupal\sms\Exception\SmsException;
use Drupal\sms\Message\SmsMessageResultInterface;
use Drupal\sms\Message\SmsDeliveryReport;
use Drupal\sms\Message\SmsMessageResult;

/**
 * Simulate a message being sent or received.
 */
class SmsDevelMessageForm extends FormBase {

  /**
   * The SMS Provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected $smsProvider;

  /**
   * The message.
   *
   * @var \Drupal\sms\Entity\SmsMessageInterface
   */
  protected $message;

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
  public function getFormId() {
    return 'sms_devel_message_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $results = $form_state->getTemporaryValue('results');

    if ($results) {
      $form = array_merge($form, $this->verboseResults($results));
    }

    $form['number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone number'),
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
    ];

    $gateways = [];
    foreach (SmsGateway::loadMultiple() as $sms_gateway) {
      $gateways[$sms_gateway->id()] = $sms_gateway->label();
    }

    $form['gateway'] = [
      '#type' => 'select',
      '#title' => $this->t('Gateway'),
      '#description' => $this->t('Select a gateway to route the message. The <em>automatic</em> option uses internal rules to decide the destination gateway. The <em>automatic</em> option can not be used if receiving a message.'),
      '#options' => $gateways,
      '#empty_option' => '- Automatic -',
    ];

    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('Options'),
      '#open' => TRUE,
    ];
    $form['options']['skip_queue'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force skip queue'),
      '#description' => $this->t('Send or receive the message immediately. If the gateway-specific skip queue setting is turned on, then this option is already applied.'),
      '#default_value' => TRUE,
      '#name' => 'skip_queue',
    ];
    $form['options']['automated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automated'),
      '#description' => $this->t('Flag this message as automated.'),
      '#default_value' => TRUE,
    ];
    $form['options']['verbose'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verbose output'),
      '#description' => $this->t('Show full details of messages.'),
      '#default_value' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="' . $form['options']['skip_queue']['#name'] . '"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['options']['send_on'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Send on'),
      '#description' => $this->t('Send this message on this date. This option only applies to messages in the queue.'),
      '#default_value' => new DrupalDateTime('now'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['receive'] = [
      '#type' => 'submit',
      '#name' => 'receive',
      '#value' => $this->t('Receive'),
      '#submit' => ['::submitReceive'],
    ];
    $form['actions']['submit'] = [
      '#name' => 'send',
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#submit' => ['::submitSend'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $number = $form_state->getValue('number');
    $message = $form_state->getValue('message');
    $automated = !empty($form_state->getValue('automated'));
    $this->message = SmsMessage::create()
      ->addRecipient($number)
      ->setMessage($message)
      ->setAutomated($automated);

    $send_on = $form_state->getValue('send_on');
    if ($send_on instanceof DrupalDateTime) {
      $this->message->setSendTime($send_on->format('U'));
    }

    $triggering_element = $form_state->getTriggeringElement();
    $gateway = $form_state->getValue('gateway');
    if (!empty($gateway)) {
      $this->message->setGateway(SmsGateway::load($gateway));
    }
    elseif ($triggering_element['#name'] == 'receive') {
      $form_state->setError($form['gateway'], $this->t('Gateway must be selected if receiving a message.'));
    }
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitReceive(array &$form, FormStateInterface $form_state) {
    $this->message->setDirection(Direction::INCOMING);
    $result = new SmsMessageResult();

    // Create some fake reports.
    foreach ($this->message->getRecipients() as $recipient) {
      $report = (new SmsDeliveryReport())
        ->setRecipient($recipient);
      $result->addReport($report);
    }

    $this->message->setResult($result);

    if ($form_state->getValue('skip_queue')) {
      $messages = $this->smsProvider->incoming($this->message);
      foreach ($messages as $message) {
        $result = $message->getResult();
        $this->resultMessage($result);
      }
    }
    else {
      $this->smsProvider->queue($this->message);
      $this->messenger()->addMessage($this->t('Message added to the incoming queue.'));
    }

  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitSend(array &$form, FormStateInterface $form_state) {
    $this->message->setDirection(Direction::OUTGOING);

    try {
      $skip_queue = $form_state->getValue('skip_queue');
      $verbose = $form_state->getValue('verbose');
      if ($verbose && $skip_queue) {
        $messages = $this->smsProvider->send($this->message);
        $results = [];
        foreach ($messages as $message) {
          $result = $message->getResult();
          $this->resultMessage($result);
          $results[] = $result;
        }
        $form_state->setTemporaryValue('results', $results);
        $form_state->setRebuild();
      }
      else {
        $this->smsProvider->queue($this->message);
        $this->messenger()->addMessage($this->t('Message added to the outgoing queue.'));
      }
    }
    catch (SmsException $e) {
      $this->messenger()->addError($this->t('Message could not be sent: @error', [
        '@error' => $e->getMessage(),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Output a status message for a result object.
   *
   * @param \Drupal\sms\Message\SmsMessageResultInterface $result
   *   An SMS result object.
   */
  protected function resultMessage(SmsMessageResultInterface $result) {
    if ($status_code = $result->getError()) {
      $status_message = $result->getErrorMessage();
      $this->messenger()->addError($this->t('A problem occurred while attempting to process message: (code: @code) @message', [
        '@code' => $status_code,
        '@message' => $status_message,
      ]));
    }
    elseif ($report_count = count($result->getReports())) {
      $this->messenger()->addMessage($this->t('Message was processed, @count delivery reports were generated.', [
        '@count' => $report_count,
      ]));
    }
    else {
      $this->messenger()->addError($this->t('An unknown error occurred while attempting to process message. No result or reports were generated by the gateway.'));
    }
  }

  /**
   * Render message results as a HTML table.
   *
   * @param \Drupal\sms\Message\SmsMessageResultInterface[] $results
   *   Results.
   *
   * @return array
   *   A render array.
   */
  protected function verboseResults(array $results) {
    $render = [];

    // Renders plain text, or 'Undefined' message if falsey.
    $renderString = function ($value) {
      return !empty($value) ? ['#plain_text' => $value] : ['#markup' => $this->t('<em>Undefined</em>')];
    };

    // Renders a date text, or 'Undefined' message if falsey.
    $renderDate = function ($timestamp) {
      if ($timestamp) {
        $date = DrupalDateTime::createFromTimestamp($timestamp);
        return ['#plain_text' => $date->format('c')];
      }
      else {
        return ['#markup' => $this->t('<em>Undefined</em>')];
      }
    };

    $render['results'] = [
      '#type' => 'table',
      '#caption' => [
        'heading' => [
          '#type' => 'inline_template',
          '#template' => '<h2>Results</h2>',
        ],
      ],
      '#header' => [
        $this->t('Result'),
        $this->t('Error'),
        $this->t('Error Message'),
        $this->t('Credits Used'),
        $this->t('Credits Balance'),
      ],
    ];

    foreach ($results as $i => $result) {
      $row = [];
      $row[]['#plain_text'] = $this->t("#@number", ['@number' => $i]);

      $error = $result->getError();
      $row[] = $error ? ['#plain_text' => $error] : ['#markup' => $this->t('<em>Success</em>')];
      $row[] = $renderString($result->getErrorMessage());
      $row[] = $renderString($result->getCreditsUsed());
      $row[] = $renderString($result->getCreditsBalance());

      $render['results'][] = $row;

      $reports_cell = [
        '#type' => 'table',
        '#header' => [
          $this->t('Recipient'),
          $this->t('Message ID'),
          $this->t('Status'),
          $this->t('Status Message'),
          $this->t('Time Delivered'),
          $this->t('Time Queued'),
        ],
      ];
      foreach ($result->getReports() as $report) {
        $row = [];

        $row[] = $renderString($report->getRecipient());
        $row[] = $renderString($report->getMessageId());
        $row[] = $renderString($report->getStatus());
        $row[] = $renderString($report->getStatusMessage());
        $row[] = $renderDate($report->getTimeDelivered());
        $row[] = $renderDate($report->getTimeQueued());

        $reports_cell[] = $row;
      }

      $render['results'][][] = [
        '#wrapper_attributes' => [
          'colspan' => count($render['results']['#header']),
        ],
        'data' => $reports_cell,
      ];
    }

    return $render;
  }

}
