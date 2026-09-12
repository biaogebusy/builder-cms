<?php

declare(strict_types=1);

namespace Drupal\content_lock\Plugin\views\access;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Plugin\views\access\Permission;
use Drupal\views\Attribute\ViewsAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that checks a permission and if an entity type is lockable.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "content_lock_access_check",
 *   title = @Translation("Content Lock access check"),
 *   help = @Translation("Checks if user has access to a specified permission and if the view's entity type is lockable.")
 * )
 */
#[ViewsAccess(
  id: 'content_lock_access_check',
  title: new TranslatableMarkup('Content lock access check'),
  help: new TranslatableMarkup("Checks if user has access to a specified permission and if the view's entity type is lockable.")
)]
class ContentLockViewAccess extends Permission implements CacheableDependencyInterface, ContainerFactoryPluginInterface {

  /**
   * The content lock service.
   *
   * @var \Drupal\content_lock\ContentLock\ContentLockInterface
   */
  protected ContentLockInterface $contentLock;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $access = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $access->contentLock = $container->get('content_lock');
    return $access;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    if ($this->view !== NULL) {
      $entity_type = $this->view->getBaseEntityType();
      if ($entity_type instanceof EntityType) {
        return parent::access($account) && $this->contentLock->hasLockEnabled($entity_type->id());
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route): void {
    parent::alterRouteDefinition($route);
    if ($this->view !== NULL) {
      $entity_type = $this->view->getBaseEntityType();
      if ($entity_type instanceof EntityType) {
        $route->setRequirement('_content_lock_enabled_access_checker', $entity_type->id());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return ['config:content_lock.settings'];
  }

}
