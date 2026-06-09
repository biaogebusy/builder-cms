<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for ai_content_suggestions plugins.
 */
abstract class AiContentSuggestionsPluginBase extends PluginBase implements AiContentSuggestionsInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig|null
   */
  protected ?ImmutableConfig $config;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('config.factory')
    );
  }

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AiProviderPluginManager $providerPluginManager,
    ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->config = $configFactory->get('ai_content_suggestions.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function label(): TranslatableMarkup {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): TranslatableMarkup {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function operationType(): string {
    return $this->pluginDefinition['operation_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array &$form): void {
    $models = $this->getModels();
    $default_model = $this->getDefaultModel();

    $form[$this->getPluginId()] = [
      '#type' => 'fieldset',
      '#title' => $this->label(),
      '#description' => $this->description(),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#parents' => [$this->getPluginId()],
    ];
    $form[$this->getPluginId()][$this->getPluginId() . '_enabled'] = [
      '#type' => 'checkbox',
      '#default_value' => $this->isEnabled(),
      '#title' => $this->t('Enable :label.', [
        ':label' => $this->label(),
      ]),
      '#parents' => [$this->getPluginId(), $this->getPluginId() . '_enabled'],
    ];
    $form[$this->getPluginId()][$this->getPluginId() . '_model'] = [
      '#type' => 'select',
      '#options' => $models,
      '#default_value' => $default_model,
      '#title' => $this->t(':label model.', [
        ':label' => $this->label(),
      ]),
      "#empty_option" => $this->t('-- Default from AI provider --'),
      '#parents' => [$this->getPluginId(), $this->getPluginId() . '_model'],
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getPluginId() . '[' . $this->getPluginId() . '_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * Helper to get the default form structure for an alter form.
   *
   * @param array $fields
   *   An array of available fields on the content.
   *
   * @return array
   *   the form array for updating.
   */
  public function getAlterFormTemplate(array $fields): array {
    return [
      '#type' => 'details',
      '#title' => $this->label(),
      '#group' => 'advanced',
      '#tree' => TRUE,
      'target_fields' => [
        '#type' => 'select',
        '#description' => $this->t('<a href="javascript://" class="toggle_content_suggestion_fields">Select the field(s) you wish to send to the LLM</a>'),
        '#options' => $fields,
        '#default_value' => array_keys($fields),
        '#multiple' => TRUE,
        '#weight' => 0,
        '#attributes' => ['class' => ['toggle_content_suggestion_select']],
        '#attached'  => [
          'library' => ['ai_content_suggestions/ai_content_suggestions_js'],
        ],
      ],
      'response' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => $this->getAjaxId(),
        ],
        'response' => [
          '#type' => 'inline_template',
          '#template' => '{{ response }}',
          '#weight' => 0,
          '#context' => [
            'response' => [
              'heading' => [
                '#type' => 'html_tag',
                '#tag' => 'i',
                '#value' => '',
              ],
            ],
          ],
        ],
        '#weight' => 50,
      ],
      $this->getPluginId() . '_submit' => [
        '#type' => 'button',
        '#value' => $this->t('Submit'),
        '#plugin' => $this->getPluginId(),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [AiContentSuggestionsFormAlter::class, 'getPluginResponse'],
          'wrapper' => $this->getAjaxId(),
        ],
        '#weight' => 51,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getModels(bool $empty = TRUE): array {
    return $this->providerPluginManager->getSimpleProviderModelOptions($this->operationType(), $empty);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModel(): ?string {
    $value = NULL;

    if ($config = $this->config->get('plugins')) {
      if (array_key_exists($this->getPluginId(), $config)) {
        $value = $config[$this->getPluginId()];
      }
    }

    if (!$value) {
      if ($default = $this->providerPluginManager->getDefaultProviderForOperationType($this->operationType())) {
        $value = $default['provider_id'] . '__' . $default['model_id'];
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {

    // As a base check that devs can override if needed, we will check we have
    // available models.
    return count($this->getModels(FALSE)) > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    $return = FALSE;

    if ($config = $this->config->get('plugins')) {

      // If the form has added a key for our plugin, it is enabled even if the
      // model value is not set.
      if (array_key_exists($this->getPluginId(), $config)) {
        $return = TRUE;
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxId(): string {
    return 'response-' . Html::cleanCssIdentifier($this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormFieldValue(string $form_field, FormStateInterface $form_state): mixed {
    $value = NULL;

    if ($values = $form_state->getValue($this->getPluginId())) {
      if (isset($values[$form_field])) {
        $value = $values[$form_field];
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetFieldValue(FormStateInterface $form_state): mixed {
    $values = [];
    $target_fields = $this->getFormFieldValue('target_fields', $form_state);
    foreach ($target_fields as $target_field) {
      if (!$field = $form_state->getValue($target_field)) {
        $tree = explode(':', $target_field);
        $field = $form_state->getValue($tree);
      }

      if ($field) {
        if (isset($field[0]['value'])) {
          $values[] = $field[0]['value'];
        }
      }
    }

    $value = implode(PHP_EOL . PHP_EOL, $values);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetProvider(string $operation_type, string|null $preferred_model = NULL): array {
    return $this->providerPluginManager->getSetProvider($operation_type, $preferred_model);
  }

  /**
   * {@inheritdoc}
   */
  public function sendChat(string $prompt): string|TranslatableMarkup {
    $provider_config = $this->getSetProvider($this->operationType(), $this->config->get('plugins')[$this->getPluginId()]);

    /** @var \Drupal\ai\AiProviderInterface $ai_provider */
    $ai_provider = $provider_config['provider_id'];

    try {
      $messages = new ChatInput([
        new chatMessage('user', $prompt),
      ]);

      $messages->setSystemPrompt('You are helpful assistant.');

      /** @var \Drupal\ai\OperationType\Chat\ChatMessage $response */
      $response = $ai_provider->chat($messages, $provider_config['model_id'], [
        'ai_content_suggestions',
      ])->getNormalized();
      $message = trim($response->getText()) ?? $this->t('No result could be generated.');
    }
    catch (\Exception $e) {
      $message = $this->t('There was an error obtaining a response from the LLM.');
    }

    return $message;
  }

}
