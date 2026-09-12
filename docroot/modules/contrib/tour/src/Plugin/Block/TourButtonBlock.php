<?php

namespace Drupal\tour\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tour\TourHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block containing a Tour button.
 */
#[Block(
  id: "tour_button_block",
  admin_label: new TranslatableMarkup("Tour button"),
)]
class TourButtonBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
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
      $container->get('config.factory'),
      $container->get('tour.helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
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
      'tour-button',
      'js-tour-start-button',
    ];

    if ($no_tips) {
      $classes = array_merge($classes, ['toolbar-tab-empty']);
    }

    return [
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#cache' => [
          'contexts' => ['url'],
          'tags' => ['tour_settings'],
        ],
        '#value' => $no_tips ? $tour_no_avail_text : $tour_avail_text,
        '#attributes' => [
          'class' => $classes,
          'aria-haspopup' => 'dialog',
          'type' => 'button',
          'aria-disabled' => $no_tips ? 'true' : 'false',
        ],
        '#attached' => [
          'library' => [
            'tour/tour-block-styling',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'label_display' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'access tour');
  }

}
