<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Event\CheckoutCompletionRegisterEvent;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the registration after checkout pane.
 */
#[CommerceCheckoutPane(
  id: "completion_register",
  label: new TranslatableMarkup("Guest registration after checkout"),
  admin_description: new TranslatableMarkup("Allows guests to register after checkout completion."),
  display_label: new TranslatableMarkup("Account information"),
  default_step: "complete",
)]
class CompletionRegister extends CheckoutPaneBase implements CheckoutPaneInterface, ContainerFactoryPluginInterface {

  /**
   * The credentials check flood controller.
   *
   * @var \Drupal\commerce\CredentialsCheckFloodInterface
   */
  protected $credentialsCheckFlood;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The user authentication object.
   *
   * @var \Drupal\user\UserAuthenticationInterface
   */
  protected $userAuth;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The order assignment.
   *
   * @var \Drupal\commerce_order\OrderAssignmentInterface
   */
  protected $orderAssignment;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->credentialsCheckFlood = $container->get('commerce.credentials_check_flood');
    $instance->currentUser = $container->get('current_user');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->orderAssignment = $container->get('commerce_order.order_assignment');
    $instance->userAuth = $container->get('user.auth');
    $instance->languageManager = $container->get('language_manager');
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    $configuration = $this->checkoutFlow->getConfiguration();
    $guest_new_account = $configuration['guest_new_account'] ?? FALSE;
    // If a guest account will be automatically created for them, do not show
    // this pane as they do not need to register.
    // @todo should we make this visible and allow it to set their created
    // user password? UX would be weird.
    if ($guest_new_account) {
      return FALSE;
    }
    // This pane can only be shown at the end of checkout.
    if ($this->order->getState()->value == 'draft') {
      return FALSE;
    }
    if ($this->currentUser->isAuthenticated()) {
      return FALSE;
    }
    $mail = $this->order->getEmail();
    if (!$mail) {
      // An email is required, but wasn't collected.
      return FALSE;
    }
    $user_storage = $this->entityTypeManager->getStorage('user');
    $existing_user = $user_storage->loadByProperties([
      'mail' => $mail,
    ]);
    if ($existing_user) {
      // The anonymous customer already has an account on the site.
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['#theme'] = 'commerce_checkout_completion_register';
    $pane_form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#maxlength' => UserInterface::USERNAME_MAX_LENGTH,
      '#description' => $this->t("Several special characters are allowed, including space, period (.), hyphen (-), apostrophe ('), underscore (_), and the @ sign."),
      '#required' => FALSE,
      '#attributes' => [
        'class' => ['username'],
        'autocorrect' => 'off',
        'autocapitalize' => 'off',
        'spellcheck' => 'false',
      ],
      '#default_value' => '',
    ];
    $pane_form['pass'] = [
      '#type' => 'password_confirm',
      '#size' => 60,
      '#description' => $this->t('Provide a password for the new account.'),
      '#required' => TRUE,
    ];
    $pane_form['actions'] = [
      '#type' => 'actions',
    ];
    $pane_form['actions']['register'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create account'),
      '#name' => 'checkout_completion_register',
    ];

    /** @var \Drupal\user\UserInterface $account */
    $account = $this->entityTypeManager->getStorage('user')->create([]);
    $form_display = EntityFormDisplay::collectRenderDisplay($account, 'register');
    $form_display->buildForm($account, $pane_form, $form_state);

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // Validate the entity. This will ensure that the username and email are in
    // the right format and not already taken.
    $values = $form_state->getValue($pane_form['#parents']);
    $user_storage = $this->entityTypeManager->getStorage('user');
    $account = $user_storage->create([
      'mail' => $this->order->getEmail(),
      'name' => $values['name'],
      'pass' => $values['pass'],
      'status' => TRUE,
      'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
      'preferred_langcode' => $this->languageManager->getCurrentLanguage()->getId(),
      'preferred_admin_langcode' => $this->languageManager->getCurrentLanguage()->getId(),
    ]);

    /** @var \Drupal\user\UserInterface $account */
    $form_display = EntityFormDisplay::collectRenderDisplay($account, 'register');
    $form_display->extractFormValues($account, $pane_form, $form_state);
    $form_display->validateFormValues($account, $pane_form, $form_state);

    // Manually flag violations of fields not handled by the form display. This
    // is necessary as entity form displays only flag violations for fields
    // contained in the display.
    // @see \Drupal\user\AccountForm::flagViolations
    $violations = $account->validate();
    foreach ($violations->getByFields(['name', 'pass']) as $violation) {
      [$field_name] = explode('.', $violation->getPropertyPath(), 2);
      $form_state->setError($pane_form[$field_name], $violation->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $user_storage = $this->entityTypeManager->getStorage('user');
    $account = $user_storage->create([
      'pass' => $values['pass'],
      'mail' => $this->order->getEmail(),
      'name' => $values['name'],
      'status' => TRUE,
      'langcode' => $this->languageManager->getCurrentLanguage()->getId(),
      'preferred_langcode' => $this->languageManager->getCurrentLanguage()->getId(),
      'preferred_admin_langcode' => $this->languageManager->getCurrentLanguage()->getId(),
    ]);
    /** @var \Drupal\user\UserInterface $account */
    $form_display = EntityFormDisplay::collectRenderDisplay($account, 'register');
    $form_display->extractFormValues($account, $pane_form, $form_state);
    $account->save();
    user_login_finalize($account);
    $client_ip = $this->requestStack->getCurrentRequest()?->getClientIp();
    if ($client_ip) {
      $this->credentialsCheckFlood->clearAccount($client_ip, $account->getAccountName());
    }

    $this->orderAssignment->assign($this->order, $account);
    // Notify other modules.
    $event = new CheckoutCompletionRegisterEvent($account, $this->order);
    $this->eventDispatcher->dispatch($event, CheckoutEvents::COMPLETION_REGISTER);
    // Event subscribers are allowed to set a redirect url, to send the
    // customer to their orders page, for example.
    if ($url = $event->getRedirectUrl()) {
      $form_state->setRedirectUrl($url);
    }
    $this->messenger()->addStatus($this->t('Registration successful. You are now logged in.'));
  }

}
