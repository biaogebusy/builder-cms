<?php

declare(strict_types=1);

namespace Drupal\private_message\Plugin\views\filter;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters threads by the fact they are unread.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter('private_message_thread_is_unread')]
class PrivateMessageThreadIsUnread extends FilterPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly AccountInterface $currentUser,
    protected readonly ViewsHandlerManager $joinHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('plugin.manager.views.join')
    );
  }

  /**
   * Override the views query.
   */
  public function query(): void {
    $current_user_id = $this->currentUser->id();

    $definition = [
      'table' => 'pm_thread_history',
      'field' => 'thread_id',
      'left_table' => 'private_message_threads',
      'left_field' => 'id',
      'operator' => '=',
      'extra' => 'pm_thread_history.access_timestamp < private_message_threads.updated',
    ];

    $join = $this->joinHandler->createInstance('standard', $definition);
    $this->query->addRelationship('pm_thread_history', $join, 'access_timestamp');
    $this->query->addWhere(NULL, 'pm_thread_history.uid', $current_user_id);

    if (!empty($this->value)) {
      parent::query();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): MarkupInterface|string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose(): bool {
    return FALSE;
  }

}
