<?php

namespace Drupal\commerce\Menu;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\commerce\InboxMessageStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides class for the Commerce inbox action link.
 */
class InboxAction extends LocalActionDefault {

  use StringTranslationTrait;

  /**
   * Constructs a new InboxAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider to load routes by name.
   * @param \Drupal\commerce\InboxMessageStorageInterface $inboxMessageStorage
   *   The inbox message storage.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RouteProviderInterface $route_provider,
    protected InboxMessageStorageInterface $inboxMessageStorage,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_provider);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.route_provider'),
      $container->get('commerce.inbox_message_storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(?Request $request = NULL) {
    $count = $this->inboxMessageStorage->getUnreadCount();
    return $count ? $this->t('Commerce inbox (@count)', ['@count' => $count]) : $this->t('Commerce inbox');
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {
    $options = parent::getOptions($route_match);
    $options['attributes']['class'][] = 'commerce-inbox';
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['commerce_inbox_message'];
  }

}
