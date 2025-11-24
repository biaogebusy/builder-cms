<?php

declare(strict_types=1);

namespace Drupal\private_message\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\user\UserInterface;

/**
 * User unban confirmation form.
 */
class ConfirmUnbanUserForm extends ConfirmFormBase {

  use AutowireTrait;

  /**
   * The user to unban.
   */
  private UserInterface $user;

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PrivateMessageBanManagerInterface $privateMessageBanManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'private_message_confirm_unblock_user_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array {
    $this->user = $user;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Add a security if the user id is unknown.
    if (empty($this->user)) {
      $form_state->setError($form, $this->t('The user id is unknown.'));
    }

    // Add security to prevent unblocking ourselves.
    if ($this->user->id() === $this->currentUser->id()) {
      $form_state->setError($form, $this->t("You can't unblock yourself."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('private_message.private_message_page');

    // If user not banned, do nothing.
    if (!$this->privateMessageBanManager->isBanned($this->user->id())) {
      return;
    }

    $this->privateMessageBanManager->unbanUser($this->user->id());
    $this->messenger()->addStatus($this->t('The user %name has been unbanned.', [
      '%name' => $this->user->getDisplayName(),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to unblock user <em>%user</em>?', [
      '%user' => $this->user->getDisplayName(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('By confirming, you will be able to send messages to this user.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('private_message.ban_page');
  }

}
