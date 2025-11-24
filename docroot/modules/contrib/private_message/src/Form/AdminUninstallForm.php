<?php

declare(strict_types=1);

namespace Drupal\private_message\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\private_message\Service\PrivateMessageUninstallerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the admin uninstall form for the Private Message module.
 */
class AdminUninstallForm extends ConfirmFormBase {

  public function __construct(
    protected PrivateMessageUninstallerInterface $privateMessageUninstaller,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('private_message.uninstaller')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'private_message_admin_uninstall_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete all private message content from the system?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('private_message.admin_config.uninstall');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup|string {
    return $this->t('Clicking the button below will delete all private message content from the system, allowing the module to be uninstalled.') . '<br><strong>' . $this->t('THIS ACTION CANNOT BE REVERSED') . '</strong>';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->privateMessageUninstaller->initiateBatch();

    $this->messenger()
      ->addMessage($this->t('Private message data has been deleted.'));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
