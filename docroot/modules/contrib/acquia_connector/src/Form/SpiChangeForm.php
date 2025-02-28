<?php

namespace Drupal\acquia_connector\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Change SPI Data form.
 *
 * @package Drupal\acquia_connector\Form
 */
class SpiChangeForm extends ConfigFormBase {

  /**
   * The state interface.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a \Drupal\acquia_connector\Form object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The State handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state) {
    parent::__construct($config_factory);

    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['acquia_connector.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'acquia_connector_spi_change_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('acquia_connector.settings');
    $blocked = $config->get('spi.blocked');
    $acquia_hosted = \Drupal::service('acquia_connector.spi')->checkAcquiaHosted();
    $environment_change = \Drupal::service('acquia_connector.spi')->checkEnvironmentChange();

    if (!$environment_change && !$blocked) {
      $form['#markup'] = $this->t("<h2>No changes detected</h2><p>This form is used to address changes in your site's environment. No changes are currently detected.</p>");
      return $form;
    }
    elseif ($blocked) {
      $form['env_change_action'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('The Acquia Connector is disabled and is not sending site profile data to Acquia Cloud for evaluation.'),
        '#options' => [
          'unblock' => $this->t('Enable this site and send data to Acquia Cloud.'),
        ],
        '#required' => TRUE,
      ];
    }
    else {
      $env_changes = $config->get('spi.environment_changes');
      $off_acquia_hosting = array_key_exists('acquia_hosted', $env_changes) && !$acquia_hosted;

      $form['env'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('<strong>The following changes have been detected in your site environment:</strong>'),
        '#description' => [
          '#theme' => 'item_list',
          '#items' => $env_changes,
        ],
      ];

      $form['env_change_action'] = [
        '#type' => 'radios',
        '#title' => $this->t('How would you like to proceed?'),
        '#options' => [
          'block' => $this->t('Disable this site from sending profile data to Acquia Cloud.'),
          'update' => $this->t('Update existing site with these changes.'),
          'create' => $this->t('Track this as a new site on Acquia Cloud.'),
        ],
        '#required' => TRUE,
        '#default_value' => $config->get('spi.environment_changed_action'),
      ];

      $form['identification'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Site Identification'),
        '#collapsible' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="env_change_action"]' => ['value' => 'create'],
          ],
        ],
      ];

      $form['identification']['site'] = [
        '#prefix' => '<div class="acquia-identification">',
        '#suffix' => '</div>',
        '#weight' => -2,
      ];

      $form['identification']['site']['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#maxlength' => 255,
        '#required' => TRUE,
        '#default_value' => $this->state->get('spi.site_name'),
      ];

      $form['identification']['site']['machine_name'] = [
        '#type' => 'machine_name',
        '#title' => $this->t('Machine name'),
        '#maxlength' => 255,
        '#required' => TRUE,
        '#machine_name' => [
          'exists' => [$this, 'exists'],
          'source' => ['identification', 'site', 'name'],
        ],
        '#default_value' => $this->state->get('spi.site_machine_name'),
      ];

      if ($acquia_hosted) {
        $form['identification']['site']['name']['#disabled'] = TRUE;
        $form['identification']['site']['machine_name']['#disabled'] = TRUE;
        $form['identification']['site']['machine_name']['#default_value'] = \Drupal::service('acquia_connector.spi')->getAcquiaHostedMachineName();
      }
      elseif ($off_acquia_hosting) {
        unset($form['env_change_action']['#options']['block']);
        unset($form['env_change_action']['#options']['update']);
        unset($form['env_change_action']['#states']);
        unset($form['identification']['site']['name']['#default_value']);
        unset($form['identification']['site']['machine_name']['#default_value']);
        $form['env_change_action']['#default_value'] = 'create';
        $form['env_change_action']['#access'] = FALSE;
      }

    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Determines if the machine name already exists.
   *
   * @return bool
   *   FALSE.
   */
  public function exists() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $values = $form_state->getValues();
    $config = $this->configFactory()->getEditable('acquia_connector.settings');

    if (isset($values['env_change_action']['unblock']) && $values['env_change_action']['unblock'] == 'unblock') {
      $config->set('spi.environment_changed_action', $values['env_change_action']['unblock'])->save();
    }
    else {
      $config->set('spi.environment_changed_action', $values['env_change_action'])->save();
    }

    if ($values['env_change_action'] == 'create') {
      $this->state->set('spi.site_name', $values['name']);
      $this->state->set('spi.site_machine_name', $values['machine_name']);
    }
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('system.status');
  }

}
