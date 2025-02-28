<?php

namespace Drupal\private_message\Entity\Builder;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\Registry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Build handler for private messages.
 */
class PrivateMessageViewBuilder extends EntityViewBuilder {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a PrivateMessageViewBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Theme\Registry $theme_registry
   *   The theme registry.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    Registry $theme_registry,
    EntityDisplayRepositoryInterface $entity_display_repository,
    AccountProxyInterface $currentUser
  ) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): PrivateMessageViewBuilder {
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
  public function view(EntityInterface $entity, $viewMode = 'default', $langcode = NULL): array {
    $message = parent::view($entity, $viewMode, $langcode);

    $classes = ['private-message'];
    $classes[] = 'private-message-' . $viewMode;
    $classes[] = 'private-message-author-' . ($this->currentUser->id() == $entity->getOwnerId() ? 'self' : 'other');
    $id = 'private-message-' . $entity->id();

    $build['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $id,
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

    $this->moduleHandler()->alter('private_message_view', $build, $entity, $viewMode);

    return $build;
  }

}
