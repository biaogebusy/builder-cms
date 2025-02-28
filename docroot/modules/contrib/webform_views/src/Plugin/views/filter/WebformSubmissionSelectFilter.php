<?php

namespace Drupal\webform_views\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\webform_views\Plugin\views\WebformSubmissionTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Select filter based on value of a webform submission.
 *
 * @ViewsFilter("webform_submission_select_filter")
 */
class WebformSubmissionSelectFilter extends InOperator {

  use WebformSubmissionTrait;

  /**
   * Denote the option of "all" options.
   *
   * @var string
   */
  const ALL = 'all';

  protected $valueFormType = 'select';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * WebformSubmissionFieldFilter constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);
    $form['value']['#required'] = FALSE;
    unset($form['value']['#options'][self::ALL]);
  }

  /**
   * {@inheritdoc}
   */
  public function showValueForm(&$form, FormStateInterface $form_state) {
    parent::showValueForm($form, $form_state);
    $form['value']['#options'] = [self::ALL => $this->valueOptions[self::ALL]] + $form['value']['#options'];
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $element = $this->getWebformElement();

      // We need this explicit "all" option because otherwise
      // InOperator::validate() rises validation errors when we are an exposed
      // required filter without default value nor without submitted exposed
      // input.
      $this->valueOptions = [self::ALL => $this->t('All')];
      $this->valueOptions += $element['#options'];
    }
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    $accept = parent::acceptExposedInput($input);
    $identifier = $this->options['expose']['identifier'];
    if (isset($input[$identifier]) && $input[$identifier] == self::ALL) {
      return FALSE;
    }
    return $accept;
  }

}
