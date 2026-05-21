<?php

namespace Drupal\simple_oauth\Entity\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantManager;
use Drupal\simple_oauth\Plugin\ScopeGranularityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Scope Form.
 *
 * @ingroup simple_oauth
 */
class Oauth2ScopeForm extends EntityForm {

  /**
   * The scope entity.
   *
   * @var \Drupal\simple_oauth\Entity\Oauth2ScopeEntityInterface
   */
  protected $entity;

  /**
   * The scope granularity manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected PluginManagerInterface $granularityManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->granularityManager = $container->get('plugin.manager.scope_granularity');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $scope = $this->entity;
    $scope_storage = $this->entityTypeManager->getStorage('oauth2_scope');

    $form['name'] = [
      '#type' => 'machine_name',
      '#default_value' => $scope->getName(),
      '#required' => TRUE,
      '#size' => 30,
      '#maxlength' => 64,
      '#machine_name' => [
        'replace_pattern' => '[^a-z0-9_:]+',
        'exists' => [$scope_storage, 'load'],
      ],
      '#description' => $this->t('A unique name for this scope. It must only contain lowercase letters, numbers, underscores, and colons.'),
    ];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#required' => TRUE,
      '#default_value' => $scope->getDescription(),
      '#description' => $this->t('Description of the scope.'),
    ];

    $grant_type_options = Oauth2GrantManager::getAvailablePluginsAsOptions();
    $form['grant_types'] = [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#required' => TRUE,
      '#title' => $this->t('Grant types'),
      '#description' => $this->t('Enable the scope for specific grant types and optionally give a specific scope description per grant type.'),
    ];
    $grant_types = $scope->getGrantTypes();
    foreach ($grant_type_options as $grant_type_key => $grant_type_label) {
      $form['grant_types'][$grant_type_key] = [
        'status' => [
          '#type' => 'checkbox',
          '#title' => $grant_type_label,
          '#default_value' => $grant_types[$grant_type_key]['status'] ?? FALSE,
        ],
        'description' => [
          '#type' => 'textfield',
          '#title' => $this->t('Description for %grant_type', ['%grant_type' => $grant_type_label]),
          '#default_value' => $grant_types[$grant_type_key]['description'] ?? '',
          '#states' => [
            'visible' => [
              ':input[name="grant_types[' . $grant_type_key . '][status]"]' => ['checked' => TRUE],
            ],
          ],
        ],
      ];
    }

    $form['umbrella'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Umbrella'),
      '#default_value' => $scope->isUmbrella(),
      '#description' => $this->t('An umbrella scope groups multiple scopes.'),
      '#executes_submit_callback' => TRUE,
      '#submit' => ['::ajaxRebuild'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::ajaxParentAndGranularity',
        'wrapper' => 'oauth2-scope-parent-and-granularity-wrapper',
      ],
    ];
    $form['parent_and_granularity'] = [
      '#type' => 'container',
      '#prefix' => '<div id="oauth2-scope-parent-and-granularity-wrapper">',
      '#suffix' => '</div>',
    ];

    if ($scope->isUmbrella()) {
      $form['parent_and_granularity']['parent'] = [
        '#type' => 'value',
        '#value' => NULL,
      ];
      $form['parent_and_granularity']['granularity_id'] = [
        '#type' => 'value',
        '#value' => NULL,
      ];
      $form['parent_and_granularity']['granularity_configuration'] = [
        '#type' => 'value',
        '#value' => NULL,
      ];
    }
    else {
      $form['parent_and_granularity']['parent'] = [
        '#type' => 'select',
        '#title' => $this->t('Parent'),
        '#options' => $this->getParentOptions(),
        '#default_value' => $scope->getParent(),
        '#description' => $this->t('If a client requests the parent scope it also has any children scopes.'),
        '#empty_value' => '_none',
        '#access' => !$scope->isUmbrella(),
      ];

      $granularity_options = ScopeGranularityManager::getAvailablePluginsAsOptions();
      $granularity = $scope->getGranularity();
      if (!$granularity) {
        assert($granularity_options);
        $granularity = $this->granularityManager->createInstance(array_key_first($granularity_options));
      }
      $form['parent_and_granularity']['granularity_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Granularity'),
        '#options' => $granularity_options,
        '#default_value' => $granularity->getPluginId(),
        '#required' => TRUE,
        '#access' => !$scope->isUmbrella(),
        '#ajax' => [
          'callback' => '::ajaxGranularityConfiguration',
          'wrapper' => 'oauth2-scope-granularity-configuration-wrapper',
        ],
        '#executes_submit_callback' => TRUE,
        '#submit' => ['::ajaxRebuild'],
        '#limit_validation_errors' => [],
      ];
      $form['parent_and_granularity']['granularity_configuration'] = [
        '#type' => 'container',
        '#tree' => TRUE,
        '#access' => !$scope->isUmbrella(),
        '#prefix' => '<div id="oauth2-scope-granularity-configuration-wrapper">',
        '#suffix' => '</div>',
      ];

      $subform = &$form['parent_and_granularity']['granularity_configuration'];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $subform = $granularity->buildConfigurationForm($subform, $subform_state);
    }

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save scope');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    parent::copyFormValuesToEntity($entity, $form, $form_state);

    assert($entity instanceof Oauth2ScopeInterface);
    $entity->getGranularity()?->setConfiguration($form_state->getValue('granularity_configuration'));
  }

  /**
   * Form submission handler for Ajax form elements.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function ajaxRebuild(array $form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
  }

  /**
   * Ajax callback than returns the parent and granularity form elements.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The parent and granularity form elements.
   */
  public static function ajaxParentAndGranularity(array $form, FormStateInterface $form_state): array {
    return $form['parent_and_granularity'];
  }

  /**
   * Ajax callback than returns the granularity configuration form elements.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The granularity configuration elements.
   */
  public static function ajaxGranularityConfiguration(array $form, FormStateInterface $form_state): array {
    return $form['parent_and_granularity']['granularity_configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $grant_types = $form_state->getValue('grant_types');
    $enabled_grant_type = FALSE;
    foreach ($grant_types as $grant_type) {
      if ($grant_type['status']) {
        $enabled_grant_type = TRUE;
      }
    }

    if (!$enabled_grant_type) {
      $form_state->setErrorByName('grant_types', $this->t('Enabling a grant type is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this->messenger()->addMessage($this->t('The scope configuration has been saved.'));
    $form_state->setRedirect('entity.oauth2_scope.collection');
  }

  /**
   * Get the parent scope options.
   *
   * @return array
   *   Returns the parent scope options.
   */
  protected function getParentOptions(): array {
    $options = [];
    /** @var \Drupal\simple_oauth\Entity\Oauth2ScopeEntityInterface[] $scopes */
    $scopes = $this->entityTypeManager->getStorage('oauth2_scope')->loadMultiple();
    foreach ($scopes as $key => $scope) {
      // Exclude current scope and don't allow recursive reference.
      if (
        $this->entity->id() !== $key &&
        ($this->entity->isNew() || $this->entity->id() !== $scope->getParent())
      ) {
        $options[$key] = $scope->getName();
      }
    }

    return $options;
  }

}
