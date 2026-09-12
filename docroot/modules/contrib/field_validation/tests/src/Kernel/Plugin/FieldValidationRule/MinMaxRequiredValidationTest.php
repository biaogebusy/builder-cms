<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormState;
use Drupal\Tests\field_validation_legacy\Kernel\Plugin\FieldValidationRule\FieldValidationRuleBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests that Length/Range/Count constraint rules require min OR max.
 *
 * Regression test: min and max were both marked '#required' => TRUE on
 * these three rule types' config forms. Symfony's own Length/Range/Count
 * constraints only require at least one of the two - a very common
 * real-world config ("at least 3 characters", no upper bound) - so the
 * blanket '#required' silently blocked HTML5 form submission client-side,
 * with no error shown and a stale success message left on screen from the
 * previous action, making an unsaved rule look saved.
 *
 * See https://www.drupal.org/project/field_validation/issues/3616699.
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
#[Group('field_validation')]
class MinMaxRequiredValidationTest extends FieldValidationRuleBase {

  /**
   * Only Min set raises no form error.
   */
  #[TestWith(['length_constraint_rule'])]
  #[TestWith(['range_constraint_rule'])]
  #[TestWith(['count_constraint_rule'])]
  public function testOnlyMinIsAccepted(string $ruleId) {
    $plugin_manager = \Drupal::service('plugin.manager.field_validation.field_validation_rule');
    $rule = $plugin_manager->createInstance($ruleId, []);

    $form = [];
    $form_state = (new FormState())->setValues(['min' => '3', 'max' => '']);
    $rule->validateConfigurationForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Only Max set raises no form error.
   */
  #[TestWith(['length_constraint_rule'])]
  #[TestWith(['range_constraint_rule'])]
  #[TestWith(['count_constraint_rule'])]
  public function testOnlyMaxIsAccepted(string $ruleId) {
    $plugin_manager = \Drupal::service('plugin.manager.field_validation.field_validation_rule');
    $rule = $plugin_manager->createInstance($ruleId, []);

    $form = [];
    $form_state = (new FormState())->setValues(['min' => '', 'max' => '10']);
    $rule->validateConfigurationForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Neither Min nor Max set raises a clean form error.
   */
  #[TestWith(['length_constraint_rule'])]
  #[TestWith(['range_constraint_rule'])]
  #[TestWith(['count_constraint_rule'])]
  public function testNeitherMinNorMaxRaisesError(string $ruleId) {
    $plugin_manager = \Drupal::service('plugin.manager.field_validation.field_validation_rule');
    $rule = $plugin_manager->createInstance($ruleId, []);

    $form = [];
    $form_state = (new FormState())->setValues(['min' => '', 'max' => '']);
    $rule->validateConfigurationForm($form, $form_state);

    $this->assertNotEmpty($form_state->getErrors());
  }

  /**
   * Both Min and Max set raises no form error.
   */
  #[TestWith(['length_constraint_rule'])]
  #[TestWith(['range_constraint_rule'])]
  #[TestWith(['count_constraint_rule'])]
  public function testBothMinAndMaxIsAccepted(string $ruleId) {
    $plugin_manager = \Drupal::service('plugin.manager.field_validation.field_validation_rule');
    $rule = $plugin_manager->createInstance($ruleId, []);

    $form = [];
    $form_state = (new FormState())->setValues(['min' => '3', 'max' => '10']);
    $rule->validateConfigurationForm($form, $form_state);

    $this->assertEmpty($form_state->getErrors());
  }

}
