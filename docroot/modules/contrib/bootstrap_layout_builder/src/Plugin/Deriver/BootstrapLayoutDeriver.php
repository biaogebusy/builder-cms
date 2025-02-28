<?php

namespace Drupal\bootstrap_layout_builder\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Layout\LayoutDefinition;
use Drupal\bootstrap_layout_builder\Plugin\Layout\BootstrapLayout;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Makes a bootstrap layout for each layout config entity.
 */
class BootstrapLayoutDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new BootstrapLayoutDeriver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $layouts = $this->entityTypeManager->getStorage('blb_layout')->getQuery()->accessCheck(TRUE)->sort('number_of_columns', 'ASC')->execute();
    if ($layouts) {
      foreach ($layouts as $layout_id) {
        $layout = $this->entityTypeManager->getStorage('blb_layout')->load($layout_id);
        $this->derivatives[$layout->id()] = new LayoutDefinition([
          'class' => BootstrapLayout::class,
          'label' => $layout->label(),
          'id' => $layout->id(),
          'category' => 'Bootstrap',
          'regions' => $this->getRegions($layout->getNumberOfColumns()),
          'theme_hook' => 'blb_section',
          'icon_map' => $this->getIconMap($layout->getNumberOfColumns()),
          'provider' => 'bootstrap_layout_builder',
        ]);
      }
    }

    return $this->derivatives;
  }

  /**
   * Convert intger to number in letters.
   *
   * @param int $num
   *   The number that needed to be converted.
   *
   * @return string
   *   The number in letters.
   */
  private function formatNumberInLetters(int $num) {
    $numbers = [
      1 => "one",
      2 => "two",
      3 => "three",
      4 => "four",
      5 => "five",
      6 => "six",
      7 => "seven",
      8 => "eight",
      9 => "nine",
      10 => "ten",
      11 => "eleven",
      12 => "twelve",
    ];
    return $numbers[$num];
  }

  /**
   * Get the formated array of row regions based on columns count.
   *
   * @param int $columns_count
   *   The count of row columns.
   *
   * @return array
   *   The row columns 'regions'.
   */
  private function getRegions(int $columns_count) {
    $regions = [];

    for ($i = 1; $i <= $columns_count; $i++) {
      $key = 'blb_region_col_' . $i;
      $regions[$key] = [
        'label' => $this->t('Col') . ' ' . $i,
      ];
    }

    return $regions;
  }

  /**
   * Get the icon map array based on columns_count.
   *
   * @param int $columns_count
   *   The count of row columns.
   *
   * @return array
   *   The icon map array.
   */
  private function getIconMap(int $columns_count) {
    $row = [];

    for ($i = 1; $i <= $columns_count; $i++) {
      $row[] = 'square_' . $this->formatNumberInLetters($i);
    }

    $icon_map = [$row];
    return $icon_map;
  }

}
