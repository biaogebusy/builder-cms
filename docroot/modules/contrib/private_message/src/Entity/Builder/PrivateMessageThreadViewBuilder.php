<?php

namespace Drupal\private_message\Entity\Builder;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Build handler for rpivate message threads.
 */
class PrivateMessageThreadViewBuilder extends EntityViewBuilder {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a PrivateMessageThreadViewBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Theme\Registry $theme_registry
   *   The theme register.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    Registry $theme_registry,
    EntityDisplayRepositoryInterface $entity_display_repository,
    AccountProxyInterface $current_user
  ) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): PrivateMessageThreadViewBuilder {
    return new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    $build = parent::view($entity, $view_mode, $langcode);

    $classes = ['private-message-thread'];
    $classes[] = 'private-message-thread-' . $view_mode;

    $last_access_time = $entity->getLastAccessTimestamp($this->currentUser);
    $messages = $entity->getMessages();

    foreach ($messages as $message) {
      if ($last_access_time <= $message->getCreatedTime() && $message->getOwnerId() != $this->currentUser->id()) {
        $classes[] = 'unread-thread';
        break;
      }
    }

    if ($view_mode == 'inbox') {
      $url = Url::fromRoute('entity.private_message_thread.canonical', ['private_message_thread' => $entity->id()]);
      $build['inbox_link'] = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => '',
        '#weight' => 9999,
        '#attributes' => ['data-thread-id' => $entity->id(), 'class' => ['private-message-inbox-thread-link']],
      ];
    }

    if ($view_mode == 'full') {
      $tags[] = 'private_message_thread:' . $entity->id() . ':view:uid:' . $this->currentUser->id();
      $tags[] = 'private_message_inbox_block:uid:' . $this->currentUser->id();
      $tags[] = 'private_message_notification_block:uid:' . $this->currentUser->id();

      Cache::invalidateTags($tags);

      $entity->updateLastAccessTime($this->currentUser);

      $build['#prefix'] = '<div id="private-message-page"><div id="private-message-thread-' . $entity->id() . '" class="' . implode(' ', $classes) . '" data-thread-id="' . $entity->id() . '" data-last-update="' . $entity->get('updated')->value . '">';
      $build['#suffix'] = '</div></div>';
    }
    else {
      $build['#prefix'] = '<div id="private-message-thread-' . $entity->id() . '" class="' . implode(' ', $classes) . '" data-thread-id="' . $entity->id() . '" data-last-update="' . $entity->get('updated')->value . '">';
      $build['#suffix'] = '</div>';
    }

    return $build;
  }

}
