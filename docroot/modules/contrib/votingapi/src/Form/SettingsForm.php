<?php

namespace Drupal\votingapi\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form used to configure Voting API settings.
 *
 * @package Drupal\votingapi\Form
 *
 * @ingroup votingapi
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Constructs a SettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, DateFormatter $date_formatter) {
    parent::__construct($config_factory);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'votingapi_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['votingapi.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('votingapi.settings');

    $unit_options = [
      300,
      900,
      1800,
      3600,
      10800,
      21600,
      32400,
      43200,
      86400,
      172800,
      345600,
      604800,
    ];

    $options[0] = 'Immediately';
    foreach ($unit_options as $option) {
      $options[$option] = $this->dateFormatter->formatInterval($option);
    }
    $options[-1] = 'Never';

    $form['anonymous_window'] = [
      '#type' => 'select',
      '#title' => $this->t('Anonymous vote rollover'),
      '#description' => $this->t("The amount of time that must pass before two anonymous votes from the same computer are considered unique. Setting this to 'never' will eliminate most double-voting, but will make it impossible for multiple anonymous on the same computer (like internet cafe customers) from casting votes."),
      '#options' => $options,
      '#default_value' => $config->get('anonymous_window'),
    ];

    $form['user_window'] = [
      '#type' => 'select',
      '#title' => $this->t('Registered user vote rollover'),
      '#description' => $this->t("The amount of time that must pass before two registered user votes from the same user ID are considered unique. Setting this to 'never' will eliminate most double-voting for registered users."),
      '#options' => $options,
      '#default_value' => $config->get('user_window'),
    ];

    $form['calculation_schedule'] = [
      '#type' => 'radios',
      '#title' => $this->t('Calculation schedule'),
      '#description' => $this->t('On high-traffic sites, administrators can use this setting to postpone the calculation of vote results.'),
      '#default_value' => $config->get('calculation_schedule'),
      '#options' => [
        'immediate' => $this->t('Tally results whenever a vote is cast'),
        'cron' => $this->t('Tally results at cron-time'),
        'manual' => $this->t('Do not tally results automatically: I am using a module that manages its own vote results.'),
      ],
      '#required' => TRUE,
    ];

    $form['delete_everywhere'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete everywhere'),
      '#description' => $this->t("Allow deleting votes to someone else's entity when owner user of this votes is deleting."),
      '#default_value' => $config->get('delete_everywhere'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('votingapi.settings');
    $settings = [
      'anonymous_window',
      'user_window',
      'calculation_schedule',
      'delete_everywhere',
    ];
    foreach ($settings as $setting) {
      $config->set($setting, $form_state->getValue($setting));
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
