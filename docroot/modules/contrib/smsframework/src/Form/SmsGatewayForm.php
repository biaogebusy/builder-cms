<?php

declare(strict_types = 1);

namespace Drupal\sms\Form;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sms\Plugin\SmsGatewayPluginManagerInterface;
use Drupal\sms\Entity\SmsGateway;
use Drupal\sms\Direction;
use Drupal\Component\Utility\NestedArray;

/**
 * Form controller for SMS Gateways.
 */
class SmsGatewayForm extends EntityForm {

  /**
   * Gateway storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $gatewayStorage;

  /**
   * Constructs a new SmsGatewayForm.
   *
   * @param \Drupal\Core\Routing\RouteBuilderInterface $routeBuilder
   *   The route builder.
   * @param \Drupal\Core\Routing\RequestContext $requestContext
   *   The request context.
   * @param \Drupal\Core\Access\AccessManagerInterface $accessManager
   *   The access manager.
   * @param \Drupal\sms\Plugin\SmsGatewayPluginManagerInterface $gatewayManager
   *   The gateway manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(
    protected RouteBuilderInterface $routeBuilder,
    protected RequestContext $requestContext,
    protected AccessManagerInterface $accessManager,
    protected SmsGatewayPluginManagerInterface $gatewayManager,
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->setMessenger($messenger);
    $this->gatewayStorage = $entityTypeManager->getStorage('sms_gateway');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('router.builder'),
      $container->get('router.request_context'),
      $container->get('access_manager'),
      $container->get('plugin.manager.sms_gateway'),
      $container->get('messenger'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway */
    $sms_gateway = $this->getEntity();

    if (!$sms_gateway->isNew()) {
      $form['#title'] = $this->t('Edit gateway %label', [
        '%label' => $sms_gateway->label(),
      ]);
    }

    $form['gateway'] = [
      '#type' => 'details',
      '#title' => $this->t('Gateway'),
      '#open' => TRUE,
    ];

    $form['gateway']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $sms_gateway->label(),
      '#required' => TRUE,
    ];

    $form['gateway']['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#default_value' => $sms_gateway->id(),
      '#machine_name' => [
        'source' => ['gateway', 'label'],
        'exists' => [$this, 'exists'],
        'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        'error' => 'The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores.',
      ],
      '#disabled' => !$sms_gateway->isNew(),
    ];

    $form['gateway']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#description' => $this->t('Enable this gateway?'),
      '#default_value' => $sms_gateway->status(),
    ];

    $plugins = [];
    foreach ($this->gatewayManager->getDefinitions() as $plugin_id => $definition) {
      $plugins[$plugin_id] = $definition['label'];
    }

    $form['gateway']['plugin_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Gateway'),
      '#options' => $plugins,
      '#required' => TRUE,
      '#disabled' => !$sms_gateway->isNew(),
      '#default_value' => !$sms_gateway->isNew() ? $sms_gateway->getPlugin()->getPluginId() : '',
    ];

    $form['message_queue'] = [
      '#type' => 'details',
      '#title' => $this->t('Message queue'),
      '#open' => TRUE,
    ];

    $form['message_queue']['skip_queue'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip queue'),
      '#description' => $this->t('Whether messages sent with this gateway skip the load balancing queue and process immediately. Only turn on this setting when debugging, do not use it on production sites.'),
      '#default_value' => $sms_gateway->getSkipQueue(),
    ];

    $form['message_queue']['retention_duration_incoming'] = [
      '#type' => 'number',
      '#title' => $this->t('Incoming message retention'),
      '#description' => $this->t('How many seconds to keep messages after they are received. Use -1 to never expire.'),
      '#field_suffix' => $this->t('seconds'),
      '#default_value' => $sms_gateway->getRetentionDuration(Direction::INCOMING),
      '#min' => -1,
    ];

    $form['message_queue']['retention_duration_outgoing'] = [
      '#type' => 'number',
      '#title' => $this->t('Outgoing message retention'),
      '#description' => $this->t('How many seconds to keep messages after they are sent. Use -1 to never expire.'),
      '#field_suffix' => $this->t('seconds'),
      '#default_value' => $sms_gateway->getRetentionDuration(Direction::OUTGOING),
      '#min' => -1,
    ];

    $form['incoming_messages'] = [
      '#type' => 'details',
      '#title' => $this->t('Incoming messages'),
      '#open' => TRUE,
      '#optional' => TRUE,
      '#tree' => TRUE,
    ];
    $form['incoming_messages']['push_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pushed messages url'),
      '#default_value' => $sms_gateway->getPushIncomingPath(),
      '#description' => $this->t('The path where incoming messages are received.'),
      '#size' => 60,
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
      '#access' => !$sms_gateway->isNew() ? $sms_gateway->autoCreateIncomingRoute() : TRUE,
      '#group' => 'incoming_messages',
    ];

    // Don't check for incoming support yet, plugin type unknown.
    if (!$sms_gateway->isNew()) {
      // Remove optional tag so the plain text element is shown.
      $form['incoming_messages']['#optional'] = FALSE;
      if (!$sms_gateway->supportsIncoming()) {
        $form['incoming_messages']['unsupported']['#plain_text'] = $this->t('This gateway does not support receiving messages.');
      }
    }

    $form['delivery_reports'] = [
      '#type' => 'details',
      '#title' => $this->t('Delivery reports'),
      '#open' => TRUE,
      '#optional' => TRUE,
    ];
    $form['delivery_reports']['#tree'] = TRUE;
    $form['delivery_reports']['push_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pushed delivery report Url'),
      '#default_value' => $sms_gateway->getPushReportPath(),
      '#description' => $this->t('The path where pushed delivery reports are received.'),
      '#size' => 60,
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
      '#access' => !$sms_gateway->isNew() ? $sms_gateway->supportsReportsPush() : TRUE,
      '#group' => 'delivery_reports',
    ];

    if (!$sms_gateway->isNew()) {
      $instance = $sms_gateway->getPlugin();
      $form += $instance->buildConfigurationForm($form, $form_state);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway */
    $sms_gateway = $this->getEntity();

    if ($sms_gateway->isNew()) {
      $sms_gateway = SmsGateway::create([
        'plugin' => $form_state->getValue('plugin_id'),
      ]);
      $this->setEntity($sms_gateway);
    }
    else {
      $sms_gateway->getPlugin()
        ->validateConfigurationForm($form, $form_state);
    }

    $path_elements_parents = [
      ['incoming_messages', 'push_path'],
      ['delivery_reports', 'push_path'],
    ];

    foreach ($path_elements_parents as $parents) {
      $element = NestedArray::getValue($form, $parents);
      $path = $form_state->getValue($parents);
      $path_length = mb_strlen($path);

      // Length must be more than 2 chars, including leading slash character.
      if ($path_length > 0) {
        if (mb_substr($path, 0, 1) !== '/') {
          $form_state->setError($element, $this->t("Path must begin with a '/' character."));
        }
        if ($path_length == 1) {
          $form_state->setError($element, $this->t("Not enough characters for path."));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway */
    $sms_gateway = $this->getEntity();

    if (!$sms_gateway->isNew()) {
      $sms_gateway->getPlugin()
        ->submitConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\sms\Entity\SmsGatewayInterface $sms_gateway */
    $sms_gateway = $this->getEntity();

    $incoming_push_path_original = $sms_gateway->getPushIncomingPath();
    $incoming_push_path = $form_state->getValue(['incoming_messages', 'push_path']);
    $reports_push_path_original = $sms_gateway->getPushReportPath();
    $reports_push_path = $form_state->getValue(['delivery_reports', 'push_path']);

    $sms_gateway
      ->setStatus($form_state->getValue('status'))
      ->setPushReportPath($reports_push_path)
      ->setPushIncomingPath($incoming_push_path);

    $saved = $sms_gateway->save();

    if ($saved == SAVED_NEW) {
      $this->messenger()->addMessage($this->t('Gateway created.'));
      $rebuild = !empty($incoming_push_path) || !empty($reports_push_path);

      // Redirect to edit form.
      $form_state->setRedirectUrl(Url::fromRoute('entity.sms_gateway.edit_form', [
        'sms_gateway' => $sms_gateway->id(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('Gateway saved.'));

      // Only rebuild routes if the paths changed.
      $rebuild_incoming = $incoming_push_path_original != $incoming_push_path;
      $rebuild_reports = $reports_push_path_original != $reports_push_path;
      $rebuild = $rebuild_incoming || $rebuild_reports;

      // Back to list page.
      $form_state->setRedirect('sms.gateway.list');
    }

    if ($rebuild) {
      $this->routeBuilder->setRebuildNeeded();
    }
  }

  /**
   * {@inheritdoc}
   *
   * Callback for `id` form element in SmsGatewayForm->buildForm.
   */
  public function exists($entity_id, array $element, FormStateInterface $form_state) {
    return (bool) $this->gatewayStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $entity_id)
      ->execute();
  }

}
