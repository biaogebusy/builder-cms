<?php

declare(strict_types=1);

namespace Drupal\private_message\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\private_message\Form\BanUserForm;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\user\UserDataInterface;

/**
 * Private message page controller. Returns render arrays for the page.
 */
class PrivateMessageController extends ControllerBase implements PrivateMessageControllerInterface {

  public function __construct(
    protected readonly UserDataInterface $userData,
    protected readonly PrivateMessageServiceInterface $privateMessageService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function privateMessagePage(): array {
    $this->privateMessageService->updateLastCheckTime();

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager()
      ->getStorage('user')
      ->load($this->currentUser()->id());

    $private_message_thread = $this->privateMessageService->getFirstThreadForUser($user);

    if ($private_message_thread) {
      $view_builder = $this->entityTypeManager()->getViewBuilder('private_message_thread');
      // No wrapper is provided, as the full view mode of the entity already
      // provides the #private-message-page wrapper.
      $page = $view_builder->view($private_message_thread);
    }
    else {
      $page = [
        '#prefix' => '<div id="private-message-page">',
        '#suffix' => '</div>',
        'no_threads' => [
          '#prefix' => '<p>',
          '#suffix' => '</p>',
          '#markup' => $this->t('You do not have any messages'),
        ],
      ];
    }

    return $page;
  }

  /**
   * {@inheritdoc}
   */
  public function pmSettingsPage(): array {
    $url = Url::fromRoute('private_message.admin_config.config')->toString();
    $message = $this->t('You can find module settings here: <a href="@url">page</a>', ['@url' => $url]);
    return [
      '#markup' => $message,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function pmThreadSettingsPage(): array {
    return [
      '#markup' => $this->t('Private Message Threads'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function configPage(): array {
    return [
      '#prefix' => '<div id="private_message_configuration_page">',
      '#suffix' => '</div>',
      'form' => $this->formBuilder()->getForm('Drupal\private_message\Form\ConfigForm'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminUninstallPage(): array {
    return [
      'message' => [
        '#prefix' => '<div id="private_message_admin_uninstall_page">',
        '#suffix' => '</div>',
        '#markup' => $this->t('The private message module cannot be uninstalled if there is private message content in the database.'),
      ],
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Delete all private message content'),
        '#url' => Url::fromRoute('private_message.admin_config.uninstall_confirm'),
        '#attributes' => [
          'class' => ['button'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function banUnbanPage(): array {
    $user_has_permission = $this->currentUser()->hasPermission('use private messaging system')
      && $this->currentUser()->hasPermission('access user profiles');

    $table = [];

    if ($user_has_permission) {
      $rows = [];
      $header = [t('User'), t('Operations')];

      /** @var \Drupal\private_message\Entity\PrivateMessageBan[] $private_message_bans */
      $private_message_bans = $this->entityTypeManager()
        ->getStorage('private_message_ban')
        ->loadByProperties(['owner' => $this->currentUser()->id()]);

      $destination = Url::fromRoute('<current>')->getInternalPath();
      foreach ($private_message_bans as $private_message_ban) {
        $label = $this->config('private_message.settings')->get('unban_label');
        $url = Url::fromRoute('private_message.unban_user_form',
          ['user' => $private_message_ban->getTargetId()],
          ['query' => ['destination' => $destination]],
        );
        $unban_link = Link::fromTextAndUrl($label, $url);

        $rows[] = [$private_message_ban->getTarget()->toLink(), $unban_link];
      }

      $table = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => t('No data found'),
      ];
    }

    return [
      '#prefix' => '<div id="private_message_ban_page">',
      '#suffix' => '</div>',
      'content' => [
        $table,
        [
          'form' => $this->formBuilder()->getForm(BanUserForm::class),
        ],
      ],
    ];
  }

}
