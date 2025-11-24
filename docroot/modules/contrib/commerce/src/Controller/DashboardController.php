<?php

namespace Drupal\commerce\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the store dashboard page.
 */
class DashboardController extends ControllerBase {

  /**
   * The menu tree.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected MenuLinkTreeInterface $menuTree;

  /**
   * The inbox message storage.
   *
   * @var \Drupal\commerce\InboxMessageStorageInterface
   */
  protected $inboxMessageStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->menuTree = $container->get('menu.link_tree');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->inboxMessageStorage = $container->get('commerce.inbox_message_storage');
    return $instance;
  }

  /**
   * Outputs the store dashboard.
   *
   * @return array
   *   A render array.
   */
  public function dashboardPage() {
    $build = [
      '#attached' => [
        'library' => ['commerce/dashboard'],
      ],
      '#prefix' => '<div class="commerce-dashboard">',
      '#suffix' => '</div>',
    ];

    if ($links = $this->getManagementLinks()) {
      $build['management_links'] = [
        '#theme' => 'commerce_dashboard_management_links',
        '#links' => $links,
        '#weight' => 0,
      ];
    }
    $build['inbox'] = $this->getInbox();
    $this->moduleHandler->alter('commerce_dashboard_page_build', $build);

    return $build;
  }

  /**
   * Outputs the dashboard modal content.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   *   A render array, or a trusted redirect response.
   */
  public function modal(Request $request) {
    $params = $request->query->all();

    if (isset($params['youtube'])) {
      return [
        'video' => [
          '#theme' => 'commerce_dashboard_video_youtube',
          '#youtube_id' => $params['youtube'],
        ],
      ];
    }

    return new Response('', 404);
  }

  /**
   * Gets the inbox content.
   *
   * @return array
   *   The renderable array.
   */
  protected function getInbox(): array {
    $conditions = [
      [
        'field' => 'state',
        'value' => 'dismissed',
        'operator' => '!=',
      ],
    ];
    $messages = $this->inboxMessageStorage->loadMultiple($conditions, 5);

    $unread_count = 0;
    foreach ($messages as $message) {
      if ($message->isUnread()) {
        $unread_count++;
      }
    }

    return [
      '#theme' => 'commerce_dashboard_inbox',
      '#unread_text' => $unread_count > 0 ? $this->t('@count unread', ['@count' => $unread_count]) : '',
      '#messages' => $messages,
      '#cache' => [
        'tags' => [
          'commerce_inbox_message',
        ],
      ],
    ];
  }

  /**
   * Gets the first level of management links from the Commerce admin menu.
   *
   * @return array
   *   The associative array.
   *
   * @see \Drupal\System\SystemManager::getAdminBlock()
   */
  protected function getManagementLinks() {
    $links = [];

    // Only get first level children of the Commerce administration link.
    $params = new MenuTreeParameters();
    $params->setRoot('commerce.admin_commerce')
      ->excludeRoot()
      ->setTopLevelOnly()
      ->onlyEnabledLinks();
    $tree = $this->menuTree->load('admin', $params);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    foreach ($tree as $key => $element) {
      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        continue;
      }

      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $element->link;
      $key = Html::getUniqueId(Html::cleanCssIdentifier($link->getRouteName(), [
        '.' => '-',
        '_' => '-',
      ]));
      $links[$key] = [
        'title' => $link->getTitle(),
        'description' => $link->getDescription(),
        'url' => Url::fromRoute($link->getRouteName(), $link->getRouteParameters()),
        'weight' => $link->getWeight(),
      ];
    }

    return $links;
  }

  /**
   * Provides callback for the commerce.inbox_message.read route.
   *
   * @param string $message_id
   *   The message ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function readInboxMessage(string $message_id): AjaxResponse {
    $response = new AjaxResponse();
    $this->inboxMessageStorage->setState($message_id, 'read');
    $unread_count = $this->inboxMessageStorage->getUnreadCount();
    if ($unread_count > 0) {
      $unread_text = $this->t('@count unread', ['@count' => $unread_count]);
      $response->addCommand(new InvokeCommand('.inbox-header__unread-text', 'text', [$unread_text->render()]));
    }
    else {
      $response->addCommand(new RemoveCommand('.inbox-header__unread-text'));
    }
    $response->addCommand(new InvokeCommand('[data-message-id="' . $message_id . '"]', 'removeClass', ['unread']));
    $response->addCommand(new InvokeCommand('[data-message-id="' . $message_id . '"]', 'addClass', ['read']));

    return $response;
  }

  /**
   * Provides callback for the commerce.inbox_message.dismiss route.
   *
   * @param string $message_id
   *   The message ID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function dismissInboxMessage(string $message_id): AjaxResponse {
    $response = new AjaxResponse();
    $this->inboxMessageStorage->setState($message_id, 'dismissed');
    $un_dismissed_count = $this->inboxMessageStorage->getUnDismissedCount();
    if ($un_dismissed_count == 0) {
      $inbox_content = $this->getInbox();
      $response->addCommand(new ReplaceCommand('.commerce-dashboard--inbox', $inbox_content));
    }
    else {
      $response->addCommand(new RemoveCommand('[data-message-id="' . $message_id . '"]'));
    }

    return $response;
  }

}
