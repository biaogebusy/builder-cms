<?php

namespace Drupal\require_login\Form;

use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides login requirements settings form.
 */
class LoginSettingsForm extends ConfigFormBase {

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected ContextRepositoryInterface $contextRepository;

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface
   */
  protected ExecutableManagerInterface $conditionPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->routeProvider = $container->get('router.route_provider');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->contextRepository = $container->get('context.repository');
    $instance->conditionPluginManager = $container->get('plugin.manager.condition');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'require_login_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['require_login.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $contexts = $this->contextRepository->getAvailableContexts();
    $form_state->setTemporaryValue('gathered_contexts', $contexts);
    $config = $this->config('require_login.settings');

    $form['login_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login path'),
      '#description' => $this->t('Path to the user login page. Defaults to /user/login when blank.'),
      '#default_value' => $config->get('login_path'),
    ];
    $form['login_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Login redirect message'),
      '#description' => $this->t('Message shown to unauthenticated user after being redirected to the login path. Leave blank to disable.'),
      '#rows' => 2,
      '#default_value' => $config->get('login_message'),
    ];
    $form['login_destination'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Login destination'),
      '#description' => $this->t('Path to predetermined login destination. Leave blank for the default behavior.'),
      '#default_value' => $config->get('login_destination'),
    ];
    $form['requirements'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Requirements'),
      [
        '#markup' => $this->t('By default all pages require user authentication. Use these conditions to further limit the user authentication requirement. All conditions must have a neutral (disabled) or positive (passed) evaluation to require user authentication. Keep in mind certain conditions have a negate option to reverse the evaluation.'),
      ],
      'conditions' => [
        '#type' => 'vertical_tabs',
        '#parents' => ['conditions'],
      ],
      '#tree' => TRUE,
    ];

    // Use the condition plugins to further limit login requirements.
    $definitions = $this->conditionPluginManager->getFilteredDefinitions('require_login', $contexts);
    $this->moduleHandler->alter('available_conditions', $definitions);
    $conditions = $config->get('requirements');
    foreach ($definitions as $id => $definition) {
      if (in_array($id, ['node_type', 'user_role', 'current_theme'], TRUE)) {
        // Skip deprecated or useless condition plugins.
        continue;
      }
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->conditionPluginManager->createInstance($id, $conditions[$id] ?? []);
      $form_state->set(['conditions', $id], $condition);
      $form['requirements'][$id] = [
        '#type' => 'details',
        '#title' => $condition->getPluginDefinition()['label'],
        '#group' => 'conditions',
      ] + $condition->buildConfigurationForm([], $form_state);
    }

    $form['requirements']['extra'] = [
      '#type' => 'details',
      '#title' => $this->t('Extra options'),
    ];
    $form['requirements']['extra']['include_403'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include 403 (access denied) page'),
      '#description' => $this->t('Disallow unauthenticated access to the 403 (access denied) page. <strong>Recommended.</strong>'),
      '#default_value' => $config->get('extra.include_403'),
    ];
    $form['requirements']['extra']['include_404'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include 404 (not found) page'),
      '#description' => $this->t('Disallow unauthenticated access to the 404 (not found) page. <strong>Recommended.</strong>'),
      '#default_value' => $config->get('extra.include_404'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('require_login.settings');
    $config->set('login_path', $form_state->getValue('login_path'));
    $config->set('login_message', $form_state->getValue('login_message'));
    $config->set('login_destination', $form_state->getValue('login_destination'));

    // Gather the requirements configurations.
    $requirements = $form_state->getValue('requirements');
    $extraRequirements = $requirements['extra'];
    unset($requirements['extra']);
    foreach ($requirements as $id => $values) {
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $form_state->get(['conditions', $id]);
      $subform = SubformState::createForSubform($form['requirements'][$id], $form, $form_state);
      $condition->submitConfigurationForm($form['requirements'][$id], $subform);
      $requirements[$id] = $condition->getConfiguration();
    }
    $config->set('requirements', $requirements);
    $config->set('extra', $extraRequirements);

    $config->save();
    parent::submitForm($form, $form_state);
    // Flush caches to ensure changes take effect immediately.
    drupal_flush_all_caches();
  }

}
