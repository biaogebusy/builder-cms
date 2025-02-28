<?php

namespace Drupal\gin;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * User picture build callback for the gin theme.
 */
class GinUserPicture implements ContainerInjectionInterface, TrustedCallbackInterface {

  /**
   * The currently authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GinUserPicture constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Lazy builder callback for the user picture.
   */
  public function build(): array {

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());

    $build = [
      '#type' => 'link',
      '#url' => $user->toUrl(),
      '#title' => [
        '#markup' => $user->getDisplayName(),
      ],
      '#attributes' => [
        'id' => 'toolbar-item-user-secondary',
        'class' => [
          'toolbar-icon',
          'toolbar-icon-user',
          'trigger',
          'toolbar-item',
        ],
        'role' => 'button',
      ],
    ];

    /** @var \Drupal\image\ImageStyleInterface $style */
    $style = NULL;
    try {
      $style = $this->entityTypeManager->getStorage('image_style')->load('thumbnail');
    }
    catch (PluginNotFoundException $e) {
      // The image style plugin does not exists. $style stays NULL and no user
      // picture will be added.
    }
    if ($style === NULL) {
      return ['link' => $build];
    }

    $file = $user->user_picture ? $user->user_picture->entity : NULL;
    if ($file === NULL) {
      return ['link' => $build];
    }

    $image_url = $style->buildUrl($file->getFileUri());

    $build['#attributes']['class'] = ['toolbar-item icon-user'];
    $build['#title'] = [
      '#type' => 'html_tag',
      '#tag' => 'img',
      '#attributes' => [
        'src' => $image_url,
        'alt' => $user->getAccountName(),
        'class' => [
          'icon-user__image',
        ],
      ],
    ];

    return ['link' => $build];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['build'];
  }

}
