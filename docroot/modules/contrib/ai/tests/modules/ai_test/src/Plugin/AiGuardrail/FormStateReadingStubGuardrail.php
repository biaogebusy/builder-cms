<?php

declare(strict_types=1);

namespace Drupal\ai_test\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailPluginBase;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test-only guardrail whose settings subform reads from the form state.
 *
 * Reading the form state is what makes SubformState resolve the subform's
 * parents, which requires `#parents` on both the subform and the parent form.
 * The guardrail entity form builds the subform before FormBuilder has assigned
 * `#parents` to the root element, so without an explicit assignment this
 * guardrail dies with "The subform and parent form must contain the #parents
 * property".
 *
 * The guardrail serialization kernel test walks every discoverable guardrail,
 * so simply existing here keeps that path covered.
 */
#[AiGuardrail(
  id: 'form_state_reading_stub_guardrail',
  label: new TranslatableMarkup('Form State Reading Stub Guardrail'),
  description: new TranslatableMarkup('Test-only guardrail whose subform reads the form state.'),
)]
class FormStateReadingStubGuardrail extends AiGuardrailPluginBase implements ConfigurableInterface, PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return ['note' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['note'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Note'),
      // Deliberately read the form state first: this is the call that throws
      // when the parent form has no `#parents`.
      '#default_value' => $form_state->getValue('note') ?? ($this->configuration['note'] ?? ''),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration(['note' => (string) $form_state->getValue('note')]);
  }

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    return new PassResult('Test guardrail, nothing checked.', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function processOutput(OutputInterface $output): GuardrailResultInterface {
    return new PassResult('Test guardrail, nothing checked.', $this);
  }

}
