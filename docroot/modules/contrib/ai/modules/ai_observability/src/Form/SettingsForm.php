<?php

namespace Drupal\ai_observability\Form;

use Drupal\ai_observability\AiLogEventType;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Observability settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_observability.settings';

  /**
   * Configuration keys mapping constants.
   */
  const CONFIG_KEY_LOGGING_ENABLED = 'logging_enabled';
  const CONFIG_KEY_LOG_EVENT_TYPES = 'log_event_types';
  const CONFIG_KEY_LOG_INPUT = 'log_input';
  const CONFIG_KEY_LOG_OUTPUT = 'log_output';
  const CONFIG_KEY_LOG_TAGS = 'log_tags';
  const CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE = 'fallback_log_message_mode';
  const CONFIG_KEY_OTEL_ENABLED = 'otel_enabled';
  const CONFIG_KEY_OTEL_SPANS = 'otel_spans';
  const CONFIG_KEY_OTEL_STORE_INPUT = 'otel_spans_store_input';
  const CONFIG_KEY_OTEL_STORE_OUTPUT = 'otel_spans_store_output';
  const CONFIG_KEY_OTEL_METRICS = 'otel_metrics';

  /**
   * Default span name for AI requests.
   */
  const OTEL_SPAN_NAME_REQUEST = 'AI provider request';

  /**
   * OpenTelemetry token usage metric name.
   */
  const OTEL_METER_NAME_TOKEN_USAGE = 'ai_observability.token_usage';

  /**
   * OpenTelemetry token usage metric prefix.
   */
  const OTEL_METRIC_TOKEN_USAGE_PREFIX = 'ai_token_usage';

  /**
   * A TypedConfigManager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected TypedConfigManagerInterface $configTyped;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The typed configuration for settings.
   *
   * @var \Drupal\Core\Config\Schema\TypedConfigInterface|null
   */
  protected $settingsTyped;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create(...func_get_args());
    $instance->moduleHandler = $container->get('module_handler');
    $instance->configTyped = $container->get('config.typed');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_observability_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    $form[self::CONFIG_KEY_LOGGING_ENABLED] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_LOGGING_ENABLED),
      '#default_value' => $config->get(self::CONFIG_KEY_LOGGING_ENABLED),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_LOGGING_ENABLED,
    ];

    $form['logger'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Logging Settings'),
      '#description' => $this->t('Allows tracking each individual request to AI providers in the Drupal Logs with token usage information, input and output data, and other useful information.'),
      '#description_display' => 'before',
      '#states' => $this->stateIfChecked(self::CONFIG_KEY_LOGGING_ENABLED),
    ];

    $log_event_options = [];
    foreach (AiLogEventType::cases() as $event) {
      $log_event_options[$event->value] = $event->label();
    }

    $form['logger'][self::CONFIG_KEY_LOG_EVENT_TYPES] = [
      '#type' => 'checkboxes',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_LOG_EVENT_TYPES),
      '#description' => $this->t('Choose which event types should be logged.'),
      '#options' => $log_event_options,
      '#default_value' => $config->get(self::CONFIG_KEY_LOG_EVENT_TYPES),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_LOG_EVENT_TYPES,
    ];

    foreach (AiLogEventType::cases() as $event) {
      $form['logger'][self::CONFIG_KEY_LOG_EVENT_TYPES][$event->value]['#description'] = $event->description();
    }

    $currentValues = $config->get(self::CONFIG_KEY_LOG_EVENT_TYPES) ?? [];
    $form['logger'][self::CONFIG_KEY_LOG_EVENT_TYPES]['#default_value'] = array_merge($currentValues, $currentValues);

    $form['logger'][self::CONFIG_KEY_LOG_INPUT] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_LOG_INPUT),
      '#description' => $this->t('Enables logging input data (input messages text).'),
      '#default_value' => $config->get(self::CONFIG_KEY_LOG_INPUT),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_LOG_INPUT,
      '#states' => $this->stateIfChecked(self::CONFIG_KEY_LOGGING_ENABLED),
    ];

    $form['logger'][self::CONFIG_KEY_LOG_OUTPUT] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_LOG_OUTPUT),
      '#description' => $this->t('Enables logging output data (provider response).'),
      '#default_value' => $config->get(self::CONFIG_KEY_LOG_OUTPUT),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_LOG_OUTPUT,
      '#states' => $this->stateIfChecked(self::CONFIG_KEY_LOGGING_ENABLED),
    ];

    $form['logger'][self::CONFIG_KEY_LOG_TAGS] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_LOG_TAGS),
      '#description' => $this->t('You can limit logging to only specific tags by entering a comma-separated list of tags. Keep empty to log events with any tag.'),
      '#default_value' => $config->get(self::CONFIG_KEY_LOG_TAGS),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_LOG_TAGS,
      '#states' => $this->stateIfChecked(self::CONFIG_KEY_LOGGING_ENABLED),
    ];

    $form['logger'][self::CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE),
      '#description' => $this->t('Enables generating the log message text using the Drupal style of placeholders, instead of the PSR3-style. This makes the log message compatible with the core Drupal Logger. Uncheck this to minimize the log entry size and increase the performance, if you use a structured logger like <a href="https://www.drupal.org/project/logger">Logger</a> or <a href="https://www.drupal.org/project/extended_logger">Extended Logger</a>.'),
      '#default_value' => $config->get(self::CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE,
    ];

    $isOtelAvailable = $this->moduleHandler->moduleExists('opentelemetry');
    $form[self::CONFIG_KEY_OTEL_ENABLED] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_OTEL_ENABLED),
      '#default_value' => $config->get(self::CONFIG_KEY_OTEL_ENABLED),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_OTEL_ENABLED,
      '#disabled' => !$isOtelAvailable,
    ];

    if (!$isOtelAvailable) {
      $form[self::CONFIG_KEY_OTEL_ENABLED]['#description'] =
        $this->t('Requires the <a href="@url">OpenTelemetry</a> module to be installed.', [
          '@url' => 'https://www.drupal.org/project/opentelemetry',
        ]);
    }

    $isOtelMetricsAvailable = $this->moduleHandler->moduleExists('opentelemetry_metrics');

    $form['opentelemetry'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Exporting to OpenTelemetry settings'),
      '#description' => $this->t('Allows exporting requests to AI providers as traces and metrics to OpenTelemetry collectors or backends.'),
      '#description_display' => 'before',
      '#states' => $this->stateIfChecked(self::CONFIG_KEY_OTEL_ENABLED),
    ];

    $form['opentelemetry'][self::CONFIG_KEY_OTEL_SPANS] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_OTEL_SPANS),
      '#description' => $this->t('Enables exporting AI request as OpenTelemetry spans.'),
      '#default_value' => $config->get(self::CONFIG_KEY_OTEL_SPANS),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_OTEL_SPANS,
      '#disabled' => !$isOtelAvailable,
    ];

    $form['opentelemetry'][self::CONFIG_KEY_OTEL_STORE_INPUT] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_OTEL_STORE_INPUT),
      '#description' => $this->t('Adds input content as a span attribute "input".'),
      '#default_value' => $config->get(self::CONFIG_KEY_OTEL_STORE_INPUT),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_OTEL_STORE_INPUT,
      '#states' => $this->stateIfChecked(self::CONFIG_KEY_OTEL_SPANS),
      '#disabled' => !$isOtelAvailable,
    ];

    $form['opentelemetry'][self::CONFIG_KEY_OTEL_STORE_OUTPUT] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_OTEL_STORE_OUTPUT),
      '#description' => $this->t('Adds output content as a span attribute "output".'),
      '#default_value' => $config->get(self::CONFIG_KEY_OTEL_STORE_OUTPUT),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_OTEL_STORE_OUTPUT,
      '#states' => $this->stateIfChecked(self::CONFIG_KEY_OTEL_SPANS),
      '#disabled' => !$isOtelAvailable,
    ];

    $form['opentelemetry'][self::CONFIG_KEY_OTEL_METRICS] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(self::CONFIG_KEY_OTEL_METRICS),
      '#default_value' => $config->get(self::CONFIG_KEY_OTEL_METRICS),
      '#config_target' => static::CONFIG_NAME . ':' . self::CONFIG_KEY_OTEL_METRICS,
      '#description' => $this->t('Enables exporting token usage as OpenTelemetry metrics.'),
      '#disabled' => !$isOtelMetricsAvailable,
    ];
    if (!$isOtelMetricsAvailable) {
      $form['opentelemetry'][self::CONFIG_KEY_OTEL_METRICS]['#description'] .= ' ' . $this->t('Requires the OpenTelemetry Metrics module to be installed.');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Returns states condition for a checkbox to be checked.
   *
   * @param string $checkboxName
   *   The name of the checkbox form element.
   *
   * @return array
   *   The states condition array.
   */
  private function stateIfChecked(string $checkboxName): array {
    return [
      'visible' => [
        ':input[name=' . $checkboxName . ']' => ['checked' => TRUE],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Apply form state values transformation on the validation step, instead of
    // the submit, because ConfigFormBase::validateForm() requires the values to
    // be valid to store in the configuration.
    $form_state->setValue(self::CONFIG_KEY_LOG_EVENT_TYPES, self::formOptionsToList($form_state->getValue(self::CONFIG_KEY_LOG_EVENT_TYPES)));
    $form_state->setValue(self::CONFIG_KEY_LOG_TAGS, array_filter(array_map('trim', explode(',', $form_state->getValue(self::CONFIG_KEY_LOG_TAGS)))));
    parent::validateForm($form, $form_state);
  }

  /**
   * Converts an array of form options into a list of selected values.
   *
   * @param array $options
   *   The submitted values from the form.
   *
   * @return array
   *   An array of values that were checked.
   */
  private static function formOptionsToList(array $options): array {
    return array_values(
      array_filter(
        $options, function ($value) {
          return $value != 0;
        }, ARRAY_FILTER_USE_BOTH
      )
    );
  }

  /**
   * Gets the label for a setting from typed settings object.
   */
  private function getSettingLabel(string $key, ?string $fallback = NULL): string {
    $this->settingsTyped ??= $this->configTyped->get(self::CONFIG_NAME);
    try {
      $label = $this->settingsTyped->get($key)->getDataDefinition()->getLabel();
    }
    catch (\InvalidArgumentException) {
      $label = $fallback ?: "[$key]";
    }
    return $label;
  }

}
