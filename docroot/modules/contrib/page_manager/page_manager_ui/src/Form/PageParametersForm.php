<?php

namespace Drupal\page_manager_ui\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for selecting page parameters.
 */
class PageParametersForm extends FormBase {

  /**
   * Machine Name of the Parameter.
   *
   * @var string
   */
  //phpcs:ignore
  protected $machine_name;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'page_manager_parameters_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
    $this->machine_name = $cached_values['id'];
    $form['items'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="available-parameters">',
      '#suffix' => '</div>',
      '#theme' => 'table',
      '#header' => [
        $this->t('Machine name'),
        $this->t('Label'),
        $this->t('Type'),
        $this->t('Optional'),
        $this->t('Operations'),
      ],
      '#rows' => $this->renderRows($cached_values, $form, $form_state),
      '#empty' => $this->t('There are no parameters defined for this page.'),
    ];
    return $form;
  }

  /**
   * Render the rows for available page parameters.
   */
  protected function renderRows($cached_values, array &$form, FormStateInterface $form_state) {
    $rows = [];
    /** @var \Drupal\page_manager\Entity\Page $page */
    $page = $cached_values['page'];
    foreach ($page->getParameterNames() as $parameter_name) {
      $parameter = $page->getParameter($parameter_name);

      // Use parameter defaults for new parameters.
      if (empty($parameter)) {
        $parameter = (array) $page->parameterDefaults();
      }

      $parameter += ['optional' => FALSE];
      $row = [];
      $row['machine_name'] = $parameter['machine_name'] ?? '';
      $row['label'] = $parameter['label'] ?? '';
      $row['type']['data'] = $parameter['type'] ?: $this->t('<em>No context assigned</em>');
      $row['optional'] = $parameter['optional'] ? $this->t('Optional') : $this->t('Required');

      [$route_partial, $route_parameters] = $this->getOperationsRouteInfo($cached_values, $cached_values['id'], $parameter_name);
      $build = [
        '#type' => 'operations',
        '#links' => $this->getOperations($route_partial, $route_parameters),
      ];
      $row['operations']['data'] = $build;
      $rows[$parameter_name] = $row;
    }

    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
  }

  /**
   * Operations for Page Parameters form.
   */
  protected function getOperations($route_name_base, array $route_parameters = []) {
    $operations['edit'] = [
      'title' => t('Edit'),
      'url' => new Url($route_name_base . '.edit', $route_parameters),
      'weight' => 10,
      'attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 700,
        ]),
      ],
    ];
    return $operations;
  }

  /**
   * Returns the tempstore id to use.
   *
   * @return string
   *   The default tempstore ID.
   */
  protected function getTempstoreId() {
    return 'page_manager.page';
  }

  /**
   * Get Operation Route Information.
   */
  protected function getOperationsRouteInfo($cached_values, $machine_name, $row) {
    return [
      'page_manager.parameter', [
        'machine_name' => $machine_name,
        'name' => $row,
      ],
    ];
  }

}
