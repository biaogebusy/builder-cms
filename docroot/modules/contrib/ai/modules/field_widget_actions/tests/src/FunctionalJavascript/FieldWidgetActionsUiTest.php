<?php

namespace Drupal\Tests\field_widget_actions\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Basic functional JS tests for the field widget actions module.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
class FieldWidgetActionsUiTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field_test',
    'field_ui',
    'field_widget_actions',
    'field_widget_actions_test',
  ];

  /**
   * The node type id.
   *
   * @var string
   */
  protected $type;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create test user.
    $admin_user = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'bypass node access',
    ]);
    $this->drupalLogin($admin_user);

    // Create content type.
    $type_name = strtolower($this->randomMachineName(8)) . '_test';
    $type = $this->drupalCreateContentType([
      'name' => $type_name,
      'type' => $type_name,
    ]);
    $this->type = $type->id();

    // Create required test field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'node',
      'type' => 'string',
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $type_name,
      'label' => 'field_test',
      'required' => TRUE,
    ]);
    $instance->save();
  }

  /**
   * Tests the suggestion-based Field Widget Action.
   */
  public function testSingleFieldWidgetActionSuggestion() {
    $this->configureFormDisplay('suggest_texts_for_textfield', 'Suggest texts');
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();

    // Open the suggestion modal and verify the suggestions are present.
    $this->click('.field--name-field-test .field-widget-action-suggest_texts_for_textfield');
    $assertSession->assertWaitOnAjaxRequest();

    $assertSession->pageTextContains('Banana');
    $assertSession->pageTextContains('Apple');
    $assertSession->pageTextContains('Kiwi');

    // Click a suggestion and verify the text field value is updated.
    $this->getSession()->getPage()->pressButton('Apple');
    $this->getSession()->wait(500);
    $assertSession->fieldValueEquals('field_test[0][value]', 'Apple');
  }

  /**
   * Tests the form-based Field Widget Action loads and works correctly.
   */
  public function testFormBasedFieldWidgetAction() {
    $this->configureFormDisplay('fill_textfield', 'Fill plain text field lorem');
    $this->drupalGet('node/add/' . $this->type);
    $assertSession = $this->assertSession();
    $assertSession->fieldExists('field_test[0][value]');

    // Click the button.
    $this->click('.field--name-field-test .field-widget-action-fill_textfield');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('New text');
    $assertSession->pageTextContains('How many times to repeat the text');

    // Fill in the form.
    $getSession = $this->getSession();
    $getSession->getPage()->fillField('new_text', 'Banana ');
    $getSession->getPage()->fillField('count', '4');
    $this->getSession()->getPage()->find('css', '.ui-dialog-buttonpane')->pressButton('Insert');

    // Check that the new text is inserted.
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->fieldValueEquals('field_test[0][value]', 'Banana Banana Banana Banana ');
  }

  /**
   * Helper to set up the form display with a specific action.
   *
   * @param string $plugin_id
   *   The plugin ID of the action.
   * @param string $label
   *   The label of the action.
   */
  protected function configureFormDisplay($plugin_id, $label) {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = EntityFormDisplay::load('node.' . $this->type . '.default');

    $display_options = [
      'type' => 'string_textfield',
      'region' => 'content',
      'settings' => [
        'size' => 60,
      ],
      'third_party_settings' => [
        'field_widget_actions' => [
          'test-uuid-1234' => [
            'enabled' => '1',
            'button_label' => $label,
            'weight' => '0',
            'plugin_id' => $plugin_id,
          ],
        ],
      ],
    ];
    $form_display->setComponent('field_test', $display_options);
    $form_display->save();
  }

}
