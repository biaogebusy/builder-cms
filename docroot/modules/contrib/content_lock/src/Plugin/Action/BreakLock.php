<?php

namespace Drupal\content_lock\Plugin\Action;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action that can break a lock.
 *
 * @Action(
 *   id = "entity:break_lock",
 *   action_label = @Translation("Break Lock"),
 *   deriver = "Drupal\content_lock\Plugin\Action\BreakLockDeriver",
 * )
 */
#[Action(
  id: 'entity:break_lock',
  action_label: new TranslatableMarkup('Break Lock'),
  deriver: BreakLockDeriver::class,
)]
class BreakLock extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a BreakLock object.
   *
   * @param mixed[] $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\content_lock\ContentLock\ContentLockInterface $lockService
   *   Content lock service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ContentLockInterface $lockService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('content_lock')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?ContentEntityInterface $object = NULL): void {
    $this->lockService->release($object);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    /** @var \Drupal\Core\Entity\EntityInterface $object */
    return $object->access('update', $account, $return_as_object);
  }

}
