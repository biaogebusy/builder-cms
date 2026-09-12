<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Form\MessageSettingsForm;

/**
 * Kernel tests for the global message settings form.
 *
 * @coversDefaultClass \Drupal\message\Form\MessageSettingsForm
 *
 * @group Message
 */
class MessageSettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'message', 'system', 'user'];

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'message', 'system', 'user']);
    $this->installEntitySchema('user');
    $this->formBuilder = $this->container->get('form_builder');
  }

  /**
   * Returns default purge method values for form submission.
   *
   * @return array
   *   Purge method form values keyed by plugin ID.
   */
  protected function purgeMethodValues(bool $quota_enabled = TRUE): array {
    return [
      'quota' => [
        'enabled' => $quota_enabled ? 1 : NULL,
        'weight' => 0,
        'data' => [],
      ],
      'days' => [
        'enabled' => NULL,
        'weight' => 1,
        'data' => [],
      ],
    ];
  }

  /**
   * Tests building the settings form.
   *
   * @covers ::getFormId
   * @covers ::getEditableConfigNames
   * @covers ::buildForm
   * @covers ::getContentEntityTypes
   * @covers ::create
   */
  public function testBuildForm(): void {
    $form_state = new FormState();
    $form = $this->formBuilder->buildForm(MessageSettingsForm::class, $form_state);

    $form_object = $form_state->getFormObject();
    $this->assertInstanceOf(MessageSettingsForm::class, $form_object);
    $this->assertEquals('message_system_settings', $form_object->getFormId());

    $this->assertArrayHasKey('settings', $form);
    $this->assertArrayHasKey('purge_enable', $form['settings']);
    $this->assertArrayHasKey('purge_methods', $form['settings']);
    $this->assertArrayHasKey('delete_on_entity_delete', $form);
    $this->assertArrayHasKey('user', $form['delete_on_entity_delete']['#options']);
  }

  /**
   * Tests submitting the settings form with purge enabled.
   *
   * @covers ::submitForm
   * @covers ::defaultKeys
   */
  public function testSubmitPurgeEnabled(): void {
    $form_state = new FormState();
    $form_state->setValues([
      'settings' => [
        // Checked checkbox must submit its return value, not boolean TRUE as
        // the only signal — 1 matches #return_value.
        'purge_enable' => 1,
        'purge_methods' => $this->purgeMethodValues(),
      ],
      'quota' => 25,
      'delete_on_entity_delete' => ['user' => 'user'],
      'op' => 'Save configuration',
    ]);
    $this->formBuilder->submitForm(MessageSettingsForm::class, $form_state);
    $this->assertFalse($form_state->hasAnyErrors(), print_r($form_state->getErrors(), TRUE));

    $config = $this->config('message.settings');
    $this->assertTrue((bool) $config->get('purge_enable'));
    $this->assertEqualsCanonicalizing(['user'], $config->get('delete_on_entity_delete'));
    $this->assertArrayHasKey('quota', $config->get('purge_methods'));
    $this->assertEquals(25, $config->get('purge_methods')['quota']['data']['quota']);
  }

  /**
   * Tests submitting the settings form with purge disabled.
   *
   * @covers ::submitForm
   */
  public function testSubmitPurgeDisabled(): void {
    $this->config('message.settings')
      ->set('purge_enable', TRUE)
      ->set('purge_methods', [
        'quota' => [
          'id' => 'quota',
          'weight' => 0,
          'data' => ['quota' => 10],
        ],
      ])
      ->save();

    $form_state = new FormState();
    $form_state->setValues([
      'settings' => [
        // Programmed forms must pass explicit NULL to uncheck a checkbox.
        // Omitting the key or passing FALSE falls back to #default_value.
        'purge_enable' => NULL,
        'purge_methods' => $this->purgeMethodValues(FALSE),
      ],
      'quota' => 25,
      'delete_on_entity_delete' => [],
      'op' => 'Save configuration',
    ]);
    $this->formBuilder->submitForm(MessageSettingsForm::class, $form_state);
    $this->assertFalse($form_state->hasAnyErrors(), print_r($form_state->getErrors(), TRUE));

    $config = $this->config('message.settings');
    $this->assertFalse((bool) $config->get('purge_enable'));
    $this->assertEquals([], $config->get('purge_methods'));
  }

}
