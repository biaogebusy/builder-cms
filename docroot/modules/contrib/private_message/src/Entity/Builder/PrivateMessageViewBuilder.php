<?php

declare(strict_types=1);

namespace Drupal\private_message\Entity\Builder;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\Registry;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Build handler for private messages.
 */
class PrivateMessageViewBuilder extends EntityViewBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    Registry $theme_registry,
    EntityDisplayRepositoryInterface $entity_display_repository,
    protected readonly AccountProxyInterface $currentUser,
  ) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    return new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'default', $langcode = NULL): array {
    assert($entity instanceof PrivateMessageInterface);

    $message = parent::view($entity, $view_mode, $langcode);

    $classes = ['private-message'];
    $classes[] = 'private-message-' . $view_mode;
    $classes[] = 'private-message-author-' . ($this->currentUser->id() == $entity->getOwnerId() ? 'self' : 'other');

    $build['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'data-message-id' => $entity->id(),
        'class' => $classes,
      ],
      '#contextual_links' => [
        'private_message' => [
          'route_parameters' => ['private_message' => $entity->id()],
        ],
      ],
    ];
    $build['wrapper']['message'] = $message;

    $this->moduleHandler()->alter('private_message_view', $build, $entity, $view_mode);

    return $build;
  }

}
