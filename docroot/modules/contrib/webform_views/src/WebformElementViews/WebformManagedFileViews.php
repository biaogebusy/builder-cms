<?php

namespace Drupal\webform_views\WebformElementViews;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\Plugin\WebformElementInterface;
use Drupal\webform\Plugin\WebformElementManagerInterface;

/**
 * Default webform views handler for managed file webform elements.
 */
class WebformManagedFileViews extends WebformElementViewsAbstract {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * WebformManagedFileViews constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\webform\Plugin\WebformElementManagerInterface $webform_element_manager
   *   Webform element manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WebformElementManagerInterface $webform_element_manager) {
    parent::__construct($entity_type_manager, $webform_element_manager);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getElementViewsData(WebformElementInterface $element_plugin, array $element) {
    $views_data = parent::getElementViewsData($element_plugin, $element);

    if ($this->entityTypeManager->hasDefinition('file')) {
      $file = $this->entityTypeManager->getDefinition('file');

      $views_data['relationship'] = [
        'base' => $file->getDataTable() ? $file->getDataTable() : $file->getBaseTable(),
        'field' => 'value',
        'base field' => $file->getKey('id'),
        'label' => $this->t('@element: File', [
          '@element' => $element['#title'],
        ]),
        'id' => 'standard',
      ];
    }

    return $views_data;
  }

}
