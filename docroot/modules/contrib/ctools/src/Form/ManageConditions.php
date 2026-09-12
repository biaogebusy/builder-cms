<?php

namespace Drupal\ctools\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manage conditions form.
 */
abstract class ManageConditions extends FormBase {

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $manager;

  /**
   * The builder of form.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The machine name.
   *
   * @var string
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  protected $machine_name;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.condition'),
      $container->get('form_builder')
    );
  }

  /**
   * Constructs a new ManageConditions object.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The condition plugin manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(PluginManagerInterface $manager, FormBuilderInterface $form_builder) {
    $this->manager = $manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ctools_manage_conditions_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
    $this->machine_name = $cached_values['id'];
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $options = [];
    $contexts = $this->getContexts($cached_values);
    foreach ($this->manager->getDefinitionsForContexts($contexts) as $plugin_id => $definition) {
      $options[$plugin_id] = (string) $definition['label'];
    }
    $form['items'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="configured-conditions">',
      '#suffix' => '</div>',
      '#theme' => 'table',
      '#header' => [$this->t('Plugin Id'), $this->t('Summary'), $this->t('Operations')],
      '#rows' => $this->renderRows($cached_values),
      '#empty' => $this->t('No required conditions have been configured.'),
    ];
    $form['conditions'] = [
      '#type' => 'select',
      '#options' => $options,
    ];
    $form['add'] = [
      '#type' => 'submit',
      '#name' => 'add',
      '#value' => $this->t('Add Condition'),
      '#ajax' => [
        'callback' => [$this, 'add'],
        'event' => 'click',
      ],
      '#submit' => [
        'callback' => [$this, 'submitForm'],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cached_values = $form_state->getTemporaryValue('wizard');
    [, $route_parameters] = $this->getOperationsRouteInfo($cached_values, $this->machine_name, $form_state->getValue('conditions'));
    $form_state->setRedirect($this->getAddRoute($cached_values), $route_parameters);
  }

  /**
   * Add a condition.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function add(array &$form, FormStateInterface $form_state) {
    $condition = $form_state->getValue('conditions');
    $content = $this->formBuilder->getForm($this->getConditionClass(), $condition, $this->getTempstoreId(), $this->machine_name);
    $content['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $cached_values = $form_state->getTemporaryValue('wizard');
    [, $route_parameters] = $this->getOperationsRouteInfo($cached_values, $this->machine_name, $form_state->getValue('conditions'));
    $route_name = $this->getAddRoute($cached_values);
    $route_options = [
      'query' => [
        FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
      ],
    ];
    $url = Url::fromRoute($route_name, $route_parameters, $route_options);
    $content['submit']['#attached']['drupalSettings']['ajax'][$content['submit']['#id']]['url'] = $url->toString();
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand($this->t('Configure Required Context'), $content, ['width' => '700']));
    return $response;
  }

  /**
   * Render the rows.
   *
   * @param mixed $cached_values
   *   The cached values.
   *
   * @return array
   *   The rendered rows.
   */
  public function renderRows($cached_values) {
    $configured_conditions = [];
    foreach ($this->getConditions($cached_values) as $row => $condition) {
      /** @var \Drupal\Core\Condition\ConditionInterface $instance */
      $instance = $this->manager->createInstance($condition['id'], $condition);
      [$route_name, $route_parameters] = $this->getOperationsRouteInfo($cached_values, $cached_values['id'], $row);
      $build = [
        '#type' => 'operations',
        '#links' => $this->getOperations($route_name, $route_parameters),
      ];
      $configured_conditions[] = [
        0 => $instance->getPluginId(),
        1 => $instance->summary(),
        'operations' => [
          'data' => $build,
        ],
      ];
    }
    return $configured_conditions;
  }

  /**
   * Get the operations.
   *
   * @param string $route_name_base
   *   The base route name.
   * @param array $route_parameters
   *   The route parameters.
   *
   * @return array
   *   The operations array.
   */
  protected function getOperations($route_name_base, array $route_parameters = []) {
    $operations['edit'] = [
      'title' => $this->t('Edit'),
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
    $route_parameters['id'] = $route_parameters['condition'];
    $operations['delete'] = [
      'title' => $this->t('Delete'),
      'url' => new Url($route_name_base . '.delete', $route_parameters),
      'weight' => 100,
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
   * Return a subclass of '\Drupal\ctools\Form\ConditionConfigure'.
   *
   * The ConditionConfigure class is designed to be subclassed with custom
   * route information to control the modal/redirect needs of your use case.
   *
   * @return string
   *   The condition class name.
   */
  abstract protected function getConditionClass();

  /**
   * The route to which condition 'add' actions should submit.
   *
   * @param mixed $cached_values
   *   The cached values.
   *
   * @return string
   *   The add route name.
   */
  abstract protected function getAddRoute($cached_values);

  /**
   * Provide the tempstore id for your specified use case.
   *
   * @return string
   *   The tempstore id.
   */
  abstract protected function getTempstoreId();

  /**
   * Document the route name and parameters for edit/delete context operations.
   *
   * The route name returned from this method is used as a "base" to which
   * ".edit" and ".delete" are appended in the getOperations() method.
   * Subclassing '\Drupal\ctools\Form\ConditionConfigure' and
   * '\Drupal\ctools\Form\ConditionDelete' should set you up for using this
   * approach quite seamlessly.
   *
   * @param mixed $cached_values
   *   The cached values.
   * @param string $machine_name
   *   The machine name.
   * @param string $row
   *   The row.
   *
   * @return array
   *   In the format of
   *   ['route.base.name', ['machine_name' => $machine_name,
   *   'context' => $row]].
   */
  abstract protected function getOperationsRouteInfo($cached_values, $machine_name, $row);

  /**
   * Custom logic for retrieving the conditions array from cached_values.
   *
   * @param mixed $cached_values
   *   The cached values.
   *
   * @return array
   *   The conditions array.
   */
  abstract protected function getConditions($cached_values);

  /**
   * Custom logic for retrieving the contexts array from cached_values.
   *
   * @param mixed $cached_values
   *   The cached values.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The contexts array.
   */
  abstract protected function getContexts($cached_values);

}
