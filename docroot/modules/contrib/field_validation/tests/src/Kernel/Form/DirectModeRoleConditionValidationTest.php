<?php

namespace Drupal\Tests\field_validation\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_validation\Form\FieldValidationRuleAddForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that "Direct" validate mode rejects role/condition scoping.
 *
 * Regression test: "Direct" validate mode attaches the Symfony constraint
 * straight to the field definition, bypassing FieldValidationConstraintValidator
 * entirely - the only place role scoping and the condition check are
 * enforced. The roles/condition form fields are only hidden via #states
 * (client-side only), not removed, so a value set before switching to
 * Direct was previously saved and silently never enforced, with no
 * warning to the admin.
 *
 * See https://www.drupal.org/project/field_validation/issues/3616699.
 *
 * @package Drupal\Tests\field_validation\Kernel\Form
 */
#[Group('field_validation')]
class DirectModeRoleConditionValidationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'text',
    'field_validation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('field_validation_rule_set');
    $this->installConfig(['field', 'node', 'field_validation']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_direct_mode_text',
      'type' => 'text',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_direct_mode_text',
      'bundle' => 'article',
    ])->save();
  }

  /**
   * Builds the Add-rule form for a fresh NotBlank rule on the test field.
   */
  protected function buildAddRuleForm() {
    $rule_set_storage = \Drupal::entityTypeManager()->getStorage('field_validation_rule_set');
    $rule_set = $rule_set_storage->create([
      'name' => 'direct_mode_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $rule_set->save();

    $form_object = FieldValidationRuleAddForm::create(\Drupal::getContainer());
    $form_state = new FormState();
    $form_object->buildForm([], $form_state, $rule_set, 'not_blank_constraint_rule');

    return [$form_object, $form_state];
  }

  /**
   * Direct mode with a role selected is rejected with a form error.
   */
  public function testDirectModeWithRoleIsRejected() {
    [$form_object, $form_state] = $this->buildAddRuleForm();

    $form_state->setValues([
      'title' => 'Test rule',
      'field_name' => 'field_direct_mode_text',
      'column' => 'value',
      'error_message' => 'error',
      'data' => ['validate_mode' => 'direct'],
      'roles' => ['authenticated' => 'authenticated'],
      'condition' => ['field' => '', 'operator' => '', 'value' => ''],
    ]);

    $form = [];
    $form_object->validateForm($form, $form_state);

    $this->assertNotEmpty($form_state->getErrors());
  }

  /**
   * Direct mode with a condition set is rejected with a form error.
   */
  public function testDirectModeWithConditionIsRejected() {
    [$form_object, $form_state] = $this->buildAddRuleForm();

    $form_state->setValues([
      'title' => 'Test rule',
      'field_name' => 'field_direct_mode_text',
      'column' => 'value',
      'error_message' => 'error',
      'data' => ['validate_mode' => 'direct'],
      'roles' => [],
      'condition' => ['field' => 'field_direct_mode_text', 'operator' => 'equals', 'value' => 'x'],
    ]);

    $form = [];
    $form_object->validateForm($form, $form_state);

    $this->assertNotEmpty($form_state->getErrors());
  }

  /**
   * Direct mode with no roles/condition set is accepted.
   */
  public function testDirectModeWithNeitherIsAccepted() {
    [$form_object, $form_state] = $this->buildAddRuleForm();

    $form_state->setValues([
      'title' => 'Test rule',
      'field_name' => 'field_direct_mode_text',
      'column' => 'value',
      'error_message' => 'error',
      'data' => ['validate_mode' => 'direct'],
      'roles' => [],
      'condition' => ['field' => '', 'operator' => '', 'value' => ''],
    ]);

    $form = [];
    $form_object->validateForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Default mode with a role and condition set is accepted (not Direct).
   */
  public function testDefaultModeWithRoleAndConditionIsAccepted() {
    [$form_object, $form_state] = $this->buildAddRuleForm();

    $form_state->setValues([
      'title' => 'Test rule',
      'field_name' => 'field_direct_mode_text',
      'column' => 'value',
      'error_message' => 'error',
      'data' => ['validate_mode' => 'default'],
      'roles' => ['authenticated' => 'authenticated'],
      'condition' => ['field' => 'field_direct_mode_text', 'operator' => 'equals', 'value' => 'x'],
    ]);

    $form = [];
    $form_object->validateForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

}
