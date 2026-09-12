<?php

namespace Drupal\Tests\chosen_field\Functional;

use Drupal\chosen_field\Plugin\Field\FieldWidget\ChosenFieldWidget;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\field\Functional\FieldTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the Chosen widgets.
 *
 * @group Chosen
 */
#[RunTestsInSeparateProcesses]
#[Group('Chosen')]
class ChosenFieldWidgetsTest extends FieldTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'options',
    'entity_test',
    'taxonomy',
    'field_ui',
    'options_test',
    'chosen_field',
    'chosen_field_test',
  ];

  /**
   * A field with cardinality 1 to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $card1;

  /**
   * A field with cardinality 2 to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $card2;

  /**
   * Function used to setup before running the test.
   */
  protected function setUp(): void {
    parent::setUp();

    // Field storage with cardinality 1.
    $this->card1 = \Drupal::entityTypeManager()->getStorage('field_storage_config')->create([
      'field_name' => 'card1',
      'entity_type' => 'entity_test',
      'type' => 'list_integer',
      'cardinality' => 1,
      'settings' => [
        'allowed_values' => [
          // Make sure that 0 works as an option.
          0 => 'Zero',
          1 => 'One',
          // Make sure that option text is properly sanitized.
          2 => 'Some <script>dangerous</script> & unescaped <strong>markup</strong>',
          // Make sure that HTML entities in option text are not double-encoded.
          3 => 'Some HTML encoded markup with &lt; &amp; &gt;',
        ],
      ],
    ]);
    $this->card1->save();

    // Field storage with cardinality 2.
    $this->card2 = \Drupal::entityTypeManager()->getStorage('field_storage_config')->create([
      'field_name' => 'card2',
      'entity_type' => 'entity_test',
      'type' => 'list_integer',
      'cardinality' => 2,
      'settings' => [
        'allowed_values' => [
          // Make sure that 0 works as an option.
          0 => 'Zero',
          1 => 'One',
          // Make sure that option text is properly sanitized.
          2 => 'Some <script>dangerous</script> & unescaped <strong>markup</strong>',
        ],
      ],
    ]);
    $this->card2->save();

    // Create a web user.
    $this->drupalLogin($this->drupalCreateUser(['view test entity', 'administer entity_test content']));
  }

  /**
   * Tests the 'chosen_select' widget (single select).
   */
  public function testSelectListSingle() {
    // Create an instance of the 'single value' field.
    $instance = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_storage' => $this->card1,
      'bundle' => 'entity_test',
    ]);
    $instance->setRequired(TRUE);
    $instance->save();

    \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('entity_test.entity_test.default')
      ->setComponent($this->card1->getName(), [
        'type' => 'chosen_select',
      ])
      ->save();

    // Create an entity.
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test')->create([
      'user_id' => 1,
      'name' => $this->randomMachineName(),
    ]);
    $entity->save();
    $entity_init = clone $entity;

    // Display form.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');
    // A required field without any value has a "none" option.
    $this->assertSession()->elementExists('xpath', '//select[@id="edit-card1"]//option[@value="_none" and text()="- Select a value -"]');

    // With no field data, nothing is selected.
    $options = ['_none', 0, 1, 2];
    $id = 'edit-card1';
    foreach ($options as $option) {
      $option_field = $this->assertSession()->optionExists($id, $option);
      $message = "Option $option for field $id is not selected.";
      $this->assertEmpty($option_field->hasAttribute('selected'), $message);
    }

    $this->assertSession()->responseContains('Some dangerous &amp; unescaped markup');

    // Submit form: select invalid 'none' option.
    $edit = ['card1' => '_none'];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains((string) new FormattableMarkup('@title field is required.', ['@title' => $instance->getName()]));

    // Submit form: select first option.
    $edit = ['card1' => 0];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity_init, 'card1', [0]);

    // Display form: check that the right options are selected.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');
    // A required field with a value has no 'none' option.
    $this->assertSession()->elementNotExists('xpath', '//select[@id="edit-card1"]//option[@value="_none"]');

    $id = 'edit-card1';
    $option = 0;
    $option_field = $this->assertSession()->optionExists($id, $option);
    $message = "Option $option for field $id is selected.";
    $this->assertNotEmpty($option_field->hasAttribute('selected'), $message);

    $options = [1, 2];
    foreach ($options as $option) {
      $option_field = $this->assertSession()->optionExists($id, $option);
      $message = "Option $option for field $id is not selected.";
      $this->assertEmpty($option_field->hasAttribute('selected'), $message);
    }

    // Make the field non required.
    $instance->setRequired(FALSE);
    $instance->save();

    // Display form.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');
    // A non-required field has a 'none' option.
    $this->assertSession()->elementExists('xpath', '//select[@id="edit-card1"]//option[@value="_none" and text()="- None -"]');
    // Submit form: Unselect the option.
    $edit = ['card1' => '_none'];
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity_init, 'card1', []);

    // Test optgroups.
    $this->card1->setSetting('allowed_values', []);
    $this->card1->setSetting('allowed_values_function', 'chosen_field_test_options_allowed_values_callback');
    $this->card1->save();

    // Display form: with no field data, nothing is selected.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');

    $options = [0, 1, 2];
    foreach ($options as $option) {
      $option_field = $this->assertSession()->optionExists($id, $option);
      $message = "Option $option for field $id is not selected.";
      $this->assertEmpty($option_field->hasAttribute('selected'), $message);
    }

    $this->assertSession()->responseContains('Some dangerous &amp; unescaped markup');
    $this->assertSession()->responseContains('Group 1');

    // Submit form: select first option.
    $edit = ['card1' => 0];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity_init, 'card1', [0]);

    // Display form: check that the right options are selected.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');

    $option = 0;
    $option_field = $this->assertSession()->optionExists($id, $option);
    $message = "Option $option for field $id is selected.";
    $this->assertNotEmpty($option_field->hasAttribute('selected'), $message);

    $options = [1, 2];
    foreach ($options as $option) {
      $option_field = $this->assertSession()->optionExists($id, $option);
      $message = "Option $option for field $id is not selected.";
      $this->assertEmpty($option_field->hasAttribute('selected'), $message);
    }

    // Submit form: Unselect the option.
    $edit = ['card1' => '_none'];
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity_init, 'card1', []);
  }

  /**
   * Tests the 'options_select' widget (multiple select).
   */
  public function testSelectListMultiple() {
    // Create an instance of the 'multiple values' field.
    $instance = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_storage' => $this->card2,
      'bundle' => 'entity_test',
    ]);
    $instance->save();

    \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('entity_test.entity_test.default')
      ->setComponent($this->card2->getName(), [
        'type' => 'chosen_select',
      ])
      ->save();

    // Create an entity.
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test')->create([
      'user_id' => 1,
      'name' => $this->randomMachineName(),
    ]);
    $entity->save();
    $entity_init = clone $entity;

    // Display form: with no field data, nothing is selected.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');

    $options = [0, 1, 2];
    $id = 'edit-card2';
    foreach ($options as $option) {
      $option_field = $this->assertSession()->optionExists($id, $option);
      $message = "Option $option for field $id is not selected.";
      $this->assertEmpty($option_field->hasAttribute('selected'), $message);
    }

    $this->assertSession()->responseContains('Some dangerous &amp; unescaped markup');

    // Submit form: select first and third options.
    $edit = ['card2[]' => [0 => 0, 2 => 2]];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity_init, 'card2', [0, 2]);

    // Display form: check that the right options are selected.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');

    $id = 'edit-card2';
    $option = 0;
    $option_field = $this->assertSession()->optionExists($id, $option);
    $message = "Option $option for field $id is selected.";
    $this->assertNotEmpty($option_field->hasAttribute('selected'), $message);

    $option = 1;
    $id = 'edit-card2';
    $option_field = $this->assertSession()->optionExists($id, $option);
    $message = "Option $option for field $id is not selected.";
    $this->assertEmpty($option_field->hasAttribute('selected'), $message);

    $id = 'edit-card2';
    $option = 2;
    $option_field = $this->assertSession()->optionExists($id, $option);
    $message = "Option $option for field $id is selected.";
    $this->assertNotEmpty($option_field->hasAttribute('selected'), $message);

    // Submit form: select only first option.
    $edit = ['card2[]' => [0 => 0]];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity_init, 'card2', [0]);

    // Display form: check that the right options are selected.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');

    $id = 'edit-card2';
    $option = 0;
    $option_field = $this->assertSession()->optionExists($id, $option);
    $message = "Option $option for field $id is selected.";
    $this->assertNotEmpty($option_field->hasAttribute('selected'), $message);

    $options = [1, 2];
    $id = 'edit-card2';
    foreach ($options as $option) {
      $option_field = $this->assertSession()->optionExists($id, $option);
      $message = "Option $option for field $id is not selected.";
      $this->assertEmpty($option_field->hasAttribute('selected'), $message);
    }

    // Submit form: select the three options while the field accepts only 2.
    $edit = ['card2[]' => [0 => 0, 1 => 1, 2 => 2]];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('this field cannot hold more than 2 values');

    // Submit form: uncheck all options.
    $edit = ['card2[]' => []];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity_init, 'card2', []);

    // A required select list does not have an empty key.
    $instance->setRequired(TRUE);
    $instance->save();
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');
    $this->assertSession()->elementNotExists('xpath', '//select[@id="edit-card2"]//option[@value=""]');

    // We do not have to test that a required select list with one option is
    // auto-selected because the browser does it for us.
    // Test optgroups.
    // Use a callback function defining optgroups.
    $this->card2->setSetting('allowed_values', []);
    $this->card2->setSetting('allowed_values_function', 'chosen_field_test_options_allowed_values_callback');
    $this->card2->save();

    $instance->setRequired(FALSE);
    $instance->save();

    // Display form: with no field data, nothing is selected.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');

    $options = [0, 1, 2];
    $id = 'edit-card2';
    foreach ($options as $option) {
      $option_field = $this->assertSession()->optionExists($id, $option);
      $message = "Option $option for field $id is not selected.";
      $this->assertEmpty($option_field->hasAttribute('selected'), $message);
    }

    $this->assertSession()->responseContains('Some dangerous &amp; unescaped markup');
    $this->assertSession()->responseContains('Group 1');

    // Submit form: select first option.
    $edit = ['card2[]' => [0 => 0]];
    $this->submitForm($edit, 'Save');
    $this->assertFieldValues($entity_init, 'card2', [0]);

    // Display form: check that the right options are selected.
    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');

    $id = 'edit-card2';
    $option = 0;
    $option_field = $this->assertSession()->optionExists($id, $option);
    $message = "Option $option for field $id is selected.";
    $this->assertNotEmpty($option_field->hasAttribute('selected'), $message);

    $options = [1, 2];
    $id = 'edit-card2';
    foreach ($options as $option) {
      $option_field = $this->assertSession()->optionExists($id, $option);
      $message = "Option $option for field $id is not selected.";
      $this->assertEmpty($option_field->hasAttribute('selected'), $message);
    }
  }

  /**
   * Tests widget-level Chosen overrides render on the select element.
   */
  public function testChosenWidgetOverrides() {
    $instance = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_storage' => $this->card1,
      'bundle' => 'entity_test',
    ]);
    $instance->save();

    \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('entity_test.entity_test.default')
      ->setComponent($this->card1->getName(), [
        'type' => 'chosen_select',
        'settings' => [
          'chosen_placeholder' => 'Pick one item',
          'no_results_text' => 'Nothing found here',
          'search_contains' => 1,
        ],
      ])
      ->save();

    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('entity_test.entity_test.default');

    $component = $display->getComponent($this->card1->getName());
    $this->assertSame('Pick one item', $component['settings']['chosen_placeholder']);
    $this->assertSame('Nothing found here', $component['settings']['no_results_text']);
    $this->assertSame(1, $component['settings']['search_contains']);

    $entity = \Drupal::entityTypeManager()->getStorage('entity_test')->create([
      'user_id' => 1,
      'name' => $this->randomMachineName(),
    ]);
    $entity->save();

    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');

    $this->assertSession()->elementAttributeContains('css', '#edit-card1', 'data-placeholder', 'Pick one item');
    $this->assertSession()->elementAttributeContains('css', '#edit-card1', 'data-no_results_text', 'Nothing found here');
    $this->assertSession()->elementAttributeContains('css', '#edit-card1', 'data-search_contains', '1');
  }

  /**
   * Tests taxonomy term auto-create support for chosen_select.
   */
  public function testTaxonomyTermAutoCreate() {
    $vocabulary = Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ]);
    $vocabulary->save();

    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
      'create terms in tags',
    ]));

    $storage = FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    $storage->save();

    $field = FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'tags' => 'tags',
          ],
          'auto_create' => TRUE,
          'auto_create_bundle' => 'tags',
        ],
      ],
    ]);
    $field->save();

    $display = EntityFormDisplay::load('entity_test.entity_test.default');
    $display
      ->setComponent('field_tags', [
        'type' => 'chosen_select',
      ])
      ->save();

    $entity = \Drupal::entityTypeManager()->getStorage('entity_test')->create([
      'user_id' => 1,
      'name' => $this->randomMachineName(),
    ]);
    $entity->save();

    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');

    $this->assertSession()->elementExists('css', '#edit-field-tags');
    $this->assertSession()->elementAttributeContains('css', '#edit-field-tags', 'data-create_option', 'true');

    // Verify the after-build callback accepts a Chosen-created client-side
    // option by adding it to the server-side #options list before validation.
    $built_element = [
      '#value' => ['New Chosen Tag'],
      '#options' => [],
      '#parents' => ['field_tags'],
    ];

    $built_element = ChosenFieldWidget::afterBuildTaxonomyTermAutoCreate($built_element, new FormState());

    $this->assertArrayHasKey('New Chosen Tag', $built_element['#options']);
    $this->assertSame('New Chosen Tag', $built_element['#options']['New Chosen Tag']);

    // Verify the callback also supports scalar submitted values, which can
    // happen for select elements before the field widget normalizes values.
    $built_element = [
      '#value' => 'Another Chosen Tag',
      '#options' => [],
      '#parents' => ['field_tags'],
    ];

    $built_element = ChosenFieldWidget::afterBuildTaxonomyTermAutoCreate($built_element, new FormState());

    $this->assertArrayHasKey('Another Chosen Tag', $built_element['#options']);
    $this->assertSame('Another Chosen Tag', $built_element['#options']['Another Chosen Tag']);

    $existing_term = Term::create([
      'vid' => 'tags',
      'name' => 'Existing tag',
    ]);
    $existing_term->save();

    /** @var \Drupal\Core\Field\WidgetPluginManager $widget_manager */
    $widget_manager = \Drupal::service('plugin.manager.field.widget');

    /** @var \Drupal\chosen_field\Plugin\Field\FieldWidget\ChosenFieldWidget $widget */
    $widget = $widget_manager->createInstance('chosen_select', [
      'field_definition' => $field,
      'settings' => [],
      'third_party_settings' => [],
    ]);

    $form_state = new FormState();

    $massaged_values = $widget->massageFormValues([
      $existing_term->id(),
      'New Chosen Tag',
    ], [], $form_state);

    $this->assertCount(2, $massaged_values);
    $this->assertSame((string) $existing_term->id(), (string) $massaged_values[0]);

    $new_term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->load($massaged_values[1]);

    $this->assertNotEmpty($new_term);
    $this->assertSame('tags', $new_term->bundle());
    $this->assertSame('New Chosen Tag', $new_term->label());
  }

  /**
   * Tests create_option is not enabled when taxonomy auto-create is disabled.
   */
  public function testTaxonomyTermCreateOptionNotEnabledWithoutAutoCreate() {
    $vocabulary = Vocabulary::create([
      'vid' => 'plain_tags',
      'name' => 'Plain tags',
    ]);
    $vocabulary->save();

    $storage = FieldStorageConfig::create([
      'field_name' => 'field_plain_tags',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    $storage->save();

    $field = FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => 'entity_test',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'plain_tags' => 'plain_tags',
          ],
          'auto_create' => FALSE,
        ],
      ],
    ]);
    $field->save();

    $display = EntityFormDisplay::load('entity_test.entity_test.default');
    $display
      ->setComponent('field_plain_tags', [
        'type' => 'chosen_select',
      ])
      ->save();

    $entity = \Drupal::entityTypeManager()->getStorage('entity_test')->create([
      'user_id' => 1,
      'name' => $this->randomMachineName(),
    ]);
    $entity->save();

    $this->drupalGet('entity_test/manage/' . $entity->id() . '/edit');

    $element = $this->assertSession()->elementExists('css', '#edit-field-plain-tags');
    $this->assertFalse($element->hasAttribute('data-create_option'));
  }

}
