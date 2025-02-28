<?php

namespace Drupal\webform_views\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\ElementInfoManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter for entity reference select webform elements.
 *
 * @ViewsFilter("webform_submission_entity_reference_select_filter")
 */
class WebformSubmissionEntityReferenceSelectFilter extends WebformSubmissionSelectFilter {

  /**
   * @var ElementInfoManager
   */
  protected $elementManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('element_info')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ElementInfoManager $element_info_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);

    $this->elementManager = $element_info_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $element = $this->getWebformElement();
      $this->elementManager->createInstance($element['#type'])->setOptions($element);

      // We need this explicit "all" option because otherwise
      // InOperator::validate() rises validation errors when we are an exposed
      // required filter without default value nor without submitted exposed
      // input.
      $this->valueOptions = [self::ALL => $this->t('All')];
      $this->valueOptions += $element['#options'];
    }
    return $this->valueOptions;
  }

}
