<?php

namespace Drupal\ai_validations\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\ai\AiProviderPluginManager;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides functionality for AiTextFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "ai_text_prompt_constraint_rule",
 *   label = @Translation("AI text prompt constraint"),
 *   description = @Translation("AI text prompt constraint.")
 * )
 */
class AiTextConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * The AI provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * {@inheritdoc}
   */
  final public function __construct($configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, Token $token_service, AiProviderPluginManager $aiProvider) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $token_service);
    $this->aiProvider = $aiProvider;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('field_validation'),
      $container->get('token'),
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string {
    return "AiTextPrompt";
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyConstraint(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'prompt' => NULL,
      'message' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Copied from core.
    $message = 'This value is not valid.';

    if ($this->configuration['prompt'] == '') {
      $this->configuration['prompt'] = 'You can only answer with XTRUE or XFALSE.
Take the following input and check if it mentions Drupal.
If it is answer XTRUE, if its not answer XFALSE. ';
    }

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#description' => $this->t('Make sure the prompt ends in such a way that we can parse the output. eg: just respond with XTRUE if (condition) otherwise answer with XFALSE'),
      '#default_value' => $this->configuration['prompt'],
      '#required' => TRUE,
    ];

    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Provider'),
      '#options' => $this->aiProvider->getSimpleProviderModelOptions('chat'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['provider'] ?? $this->aiProvider->getSimpleDefaultProviderOptions('chat'),
    ];

    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'] ?? $message,
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['prompt'] = $form_state->getValue('prompt');
    $this->configuration['message'] = $form_state->getValue('message');
    $this->configuration['provider'] = $form_state->getValue('provider');
  }

}
