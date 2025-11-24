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
use Drupal\private_message\Model\BlockType;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;
use Drupal\user\UserInterface;

/**
 * The user block confirmation form.
 */
class ConfirmBanUserForm extends ConfirmFormBase {

  use AutowireTrait;

  /**
   * The user to block.
   */
  protected UserInterface $user;

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PrivateMessageBanManagerInterface $privateMessageBanManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'private_message_confirm_block_user_form';
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
      return;
    }

    // Add security to prevent blocking ourselves.
    if ($this->user->id() === $this->currentUser->id()) {
      $form_state->setError($form, $this->t("You can't block yourself."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('private_message.private_message_page');

    // If user is already banned, do nothing.
    if ($this->privateMessageBanManager->isBanned($this->user->id())) {
      return;
    }

    $this->privateMessageBanManager->banUser($this->user->id());
    $this->messenger()->addStatus($this->t('The user %name has been banned.', [
      '%name' => $this->user->getDisplayName(),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to block user <em>%user</em>?', ['%user' => $this->user->getDisplayName()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    $config = $this->config('private_message.settings');

    if ($config->get('ban_mode') === BlockType::Passive->value) {
      return $this->t('By confirming, you will no longer be able to send messages to this user.');
    }
    else {
      return $this->t('By confirming, you will no longer be able to send messages to this user. Also, this user will no longer be able to message you.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('private_message.ban_page');
  }

}
