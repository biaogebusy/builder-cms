<?php

declare(strict_types=1);

namespace Drupal\tour\Plugin\TopBarItem;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\navigation\Attribute\TopBarItem;
use Drupal\navigation\TopBarItemBase;
use Drupal\navigation\TopBarRegion;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tour\TourHelper;
use Drupal\Core\Template\Attribute;

// cspell:ignore topbar

/**
 * Provides the tour top bar item.
 */
#[TopBarItem(
  id: 'tour_topbar',
  region: TopBarRegion::Tools,
  label: new TranslatableMarkup('tour'),
)]
class TourTopBarItem extends TopBarItemBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new TourTopBarItem instance.
   *
   * @param array $configuration
   *   A configuration array containing plugin instance information.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param mixed $tourHelper
   *   Helper methods for the tour module.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected AccountProxyInterface $currentUser,
    protected TourHelper $tourHelper,
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
      $container->get('current_user'),
      $container->get('tour.helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [
      '#cache' => [
        'contexts' => ['url'],
        'tags' => ['tour_settings'],
      ],
    ];
    // Check if the user has the required permission.
    if (!$this->currentUser->hasPermission('access tour')) {
      return $build;
    }

    return $this->buildTourNavigation();
  }

  /**
   * Builds the tour navigation render array.
   *
   * @return array
   *   A render array for the tour navigation.
   */
  protected function buildTourNavigation(): array {
    $tour_helper = $this->tourHelper;
    $results = $tour_helper->loadTourEntities();
    $no_tips = empty($results);

    if ($this->tourHelper->shouldEmptyBeHidden($no_tips)) {
      return [
        '#cache' => [
          'contexts' => ['url'],
          'tags' => ['tour_settings'],
        ],
      ];
    }

    $tour_avail_text = $tour_helper->getTourLabels()['tour_avail_text'];
    $tour_no_avail_text = $tour_helper->getTourLabels()['tour_no_avail_text'];

    $classes = [
      'js-tour-start-button',
      'toolbar-button',
      'toolbar-button--icon--help',
    ];

    if ($no_tips) {
      $classes = array_merge($classes, ['toolbar-tab-empty']);
    }

    return [
      '#type' => 'component',
      '#component' => 'navigation:toolbar-button',
      '#props' => [
        'text' => $no_tips ? $tour_no_avail_text : $tour_avail_text,
        'modifiers' => ['small-offset'],
        'icon' => ['icon_id' => 'help'],
        'html_tag' => 'button',
        'content' => $no_tips ? $tour_no_avail_text : $tour_avail_text,
        'attributes' => new Attribute([
          'href' => '',
          'aria-haspopup' => 'dialog',
          'aria-disabled' => $no_tips ? 'true' : 'false',
        ]),
        'extra_classes' => $classes,
      ],
      '#cache' => [
        'contexts' => ['url'],
        'tags' => ['tour_settings'],
      ],
      '#weight' => 10,
    ];
  }

}
