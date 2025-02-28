<?php

namespace Drupal\webform_views\WebformElementViews;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webform\Plugin\WebformElementInterface;
use Drupal\webform\Plugin\WebformElementManagerInterface;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract implementation of webform element views handler.
 */
abstract class WebformElementViewsAbstract implements WebformElementViewsInterface, ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Webform submission entity type.
   *
   * @var EntityTypeInterface
   */
  protected $entityType;

  /**
   * @var WebformElementManagerInterface
   */
  protected $webformElementManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.webform.element')
    );
  }

  /**
   * WebformElementViewsAbstract constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service
   * @param \Drupal\webform\Plugin\WebformElementManagerInterface $webform_element_manager
   *   Webform element manager service
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WebformElementManagerInterface $webform_element_manager) {
    $this->entityType = $entity_type_manager->getDefinition('webform_submission');
    $this->webformElementManager = $webform_element_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsData($element, WebformInterface $webform) {
    $table_alias = $this->tableName($element, $webform);
    $element_title = (isset($element['#title']) && $element['#title']) ? $element['#title'] : $element['#webform_key'];
    $element_plugin = $this->webformElementManager->getElementInstance($element);

    $data[$table_alias]['table']['group'] = $this->t('Webform @webform submission data', [
      '@webform' => $webform->label(),
    ]);

    // For each webform submission element we create a table alias and then
    // explain to Views how to join {webform_submission_data} onto
    // {webform_submission}.
    $data[$table_alias]['table']['join'][$this->entityType->getBaseTable()] = [
      'table' => 'webform_submission_data',
      'field' => $this->entityType->getKey('id'),
      'left_field' => $this->entityType->getKey('id'),
      'extra' => [
        ['field' => 'name', 'value' => $element['#webform_key']],
      ],
    ];

    $data[$table_alias]['webform_submission_value'] = [
      'title' => Html::escape($element_title),
      'help' => $this->t('Value of the field %field in webform %webform submission.', [
        '%field' => $element_title,
        '%webform' => $webform->label(),
      ]),
    ];

    foreach ($this->getElementViewsData($element_plugin, $element) as $k => $v) {
      $v += [
        'webform_id' => $webform->id(),
        'webform_submission_field' => $element['#webform_key'],
      ];
      $data[$table_alias]['webform_submission_value'][$k] = $v;
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getElementViewsData(WebformElementInterface $element_plugin, array $element) {
    return [
      'field' => [
        'id' => 'webform_submission_field',
        'real field' => 'value',
        'click sortable' => !$element_plugin->isContainer($element) && !$element_plugin->hasMultipleValues($element),
        'multiple' => $element_plugin->hasMultipleValues($element),
      ],
    ];
  }

  /**
   * Generate views table name that represents a given element within a webform.
   *
   * @param array $element
   *   Webform element for which to generate views table name.
   * @param \Drupal\webform\WebformInterface $webform
   *   Webform for which to generate views table name.
   *
   * @return string
   *   Views table name that represents provided webform element within provided
   *   webform.
   */
  protected function tableName($element, WebformInterface $webform) {
    return 'webform_submission_field_' . $webform->id() . '_' . $element['#webform_key'];
  }

}
