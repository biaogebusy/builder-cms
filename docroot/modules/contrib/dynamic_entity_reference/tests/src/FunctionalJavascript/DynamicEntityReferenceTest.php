<?php

namespace Drupal\Tests\dynamic_entity_reference\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Ensures that Dynamic Entity References field works correctly.
 *
 * @group dynamic_entity_reference
 * @group functional_javascript
 */
class DynamicEntityReferenceTest extends WebDriverTestBase {

  /**
   * Escape key code.
   */
  const ESCAPE_KEY = 27;

  /**
   * The admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * The another user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $anotherUser;

  /**
   * Test entity.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $testEntity;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'field_ui',
    'dynamic_entity_reference',
    'entity_test',
    'node',
  ];

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'access administration pages',
    'view test entity',
    'administer entity_test fields',
    'administer entity_test content',
    'administer node fields',
    'administer node display',
    'access user profiles',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Sets the test up.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser($this->permissions);
    $this->anotherUser = $this->drupalCreateUser();
  }

  /**
   * Sets up field for testing.
   */
  protected function setupField(string $field_name, string $entity_type_id, string $bundle, array $storage_settings, array $field_settings, string $label): void {
    // Add a new dynamic entity reference field.
    $storage = FieldStorageConfig::create([
      'entity_type' => $entity_type_id,
      'field_name' => $field_name,
      'id' => "$entity_type_id.$field_name",
      'type' => 'dynamic_entity_reference',
      'settings' => $storage_settings,
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $storage->save();
    $config = FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'bundle' => $bundle,
      'id' => "$entity_type_id.$bundle.$field_name",
      'label' => $label,
      'settings' => $field_settings,
    ]);
    $config->save();
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository
      ->getViewDisplay($entity_type_id, $bundle, 'default')
      ->setComponent($field_name, [
        'region' => 'content',
        'type' => 'dynamic_entity_reference_label',
      ])
      ->save();
    $display_repository
      ->getFormDisplay($entity_type_id, $bundle, 'default')
      ->setComponent($field_name, [
        'region' => 'content',
        'type' => 'dynamic_entity_reference_default',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
          'size' => 40,
          'placeholder' => '',
        ],
      ])
      ->save();
  }

  /**
   * Tests field settings of dynamic entity reference field.
   */
  public function testFieldSettings() {
    $assert_session = $this->assertSession();
    // Add EntityTestBundle for EntityTestWithBundle.
    EntityTestBundle::create([
      'id' => 'test',
      'label' => 'Test label',
      'description' => 'My test description',
    ])->save();
    // We will query on the first two characters of the second username.
    $autocomplete_query = mb_substr($this->anotherUser->label(), 0, 3);
    $this->testEntity = EntityTest::create([
      // Make this partially match the second username.
      'name' => $autocomplete_query . $this->randomMachineName(5),
      'type' => 'entity_test',
    ]);
    $this->testEntity->save();

    $this->drupalLogin($this->adminUser);
    $this->setupField(
      'field_foobar',
      'entity_test',
      'entity_test',
      [
        'entity_type_ids' => ['user', 'entity_test'],
        'exclude_entity_types' => FALSE,
      ],
      [
        'entity_test' => [
          'handler' => 'default:entity_test',
          'handler_settings' => [
            'target_bundles' => [
              'entity_test' => 'entity_test',
            ],
          ],
        ],
        'user' => [
          'handler' => 'default:user',
          'handler_settings' => [
            'target_bundles' => [
              'user' => 'user',
            ],
          ],
        ],
      ],
      'Foobar'
    );
    $this->drupalGet('entity_test/structure/entity_test/fields/entity_test.entity_test.field_foobar');
    $page = $this->getSession()->getPage();
    $page->checkField('set_default_value');
    $assert_session->fieldExists('default_value_input[field_foobar][0][target_id]');
    $assert_session->fieldNotExists('default_value_input[field_foobar][1][target_id]');
    $this->submitForm([], 'Add another item');
    $assert_session->assertWaitOnAjaxRequest(20000);
    $assert_session->fieldExists('default_value_input[field_foobar][1][target_id]');
    $autocomplete_field = $page->findField('default_value_input[field_foobar][0][target_id]');
    $autocomplete_field_1 = $page->findField('default_value_input[field_foobar][1][target_id]');
    $target_type_select = $assert_session->selectExists('default_value_input[field_foobar][0][target_type]');
    $entity_test_path = $this->createAutoCompletePath('entity_test');
    $this->assertSame($autocomplete_field->getAttribute('data-autocomplete-path'), $entity_test_path);
    $this->assertSame($autocomplete_field_1->getAttribute('data-autocomplete-path'), $entity_test_path);
    $target_type_select->selectOption('user');
    // Changing the selected value changes the autocomplete path for the
    // corresponding autocomplete field.
    $this->assertSession()->assertNoElementAfterWait('css', sprintf('[name="default_value_input[field_foobar][0][target_id]"][data-autocomplete-path="%s"]', $entity_test_path));
    $user_path = $this->createAutoCompletePath('user');
    $this->assertSame($autocomplete_field->getAttribute('data-autocomplete-path'), $user_path);
    // Changing the selected value of delta 0 doesn't change the autocomplete
    // path for delta 1 autocomplete field.
    $this->assertSame($autocomplete_field_1->getAttribute('data-autocomplete-path'), $entity_test_path);
    $target_type_select->selectOption('entity_test');
    // Changing the selected value changes the autocomplete path for the
    // corresponding autocomplete field.
    $this->assertSession()->assertNoElementAfterWait('css', sprintf('[name="default_value_input[field_foobar][0][target_id]"][data-autocomplete-path="%s"]', $user_path));
    $this->assertSame($autocomplete_field->getAttribute('data-autocomplete-path'), $entity_test_path);
    // Changing the selected value of delta 0 doesn't change the autocomplete
    // path for delta 1 autocomplete field.
    $this->assertSame($autocomplete_field_1->getAttribute('data-autocomplete-path'), $entity_test_path);
    $page = $this->getSession()->getPage();
    $page->checkField('settings[entity_test][handler_settings][auto_create]');
    $this->submitForm([], 'Save settings');
    $assert_session->pageTextContains('Saved Foobar configuration');
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    $this->drupalGet('entity_test/add');
    $autocomplete_field = $page->findField('field_foobar[0][target_id]');
    $entity_type_field = $page->findField('field_foobar[0][target_type]');
    // Change to user.
    $entity_type_field->selectOption('user');
    $this->performAutocompleteQuery($autocomplete_query, $autocomplete_field);
    $this->selectAutocompleteOption();
    $this->assertStringContainsString($this->anotherUser->label(), $autocomplete_field->getValue());
    // Change to entity_test, this should automatically clear the autocomplete
    // field.
    $entity_type_field->selectOption('entity_test');
    $this->assertEmpty($autocomplete_field->getValue());
    $this->performAutocompleteQuery($autocomplete_query, $autocomplete_field);
    $this->selectAutocompleteOption();
    $this->assertStringContainsString($this->testEntity->label(), $autocomplete_field->getValue());
  }

  /**
   * Tests view modes in formatter of dynamic entity reference field.
   */
  public function testFieldFormatterViewModes() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->adminUser);
    $this->drupalCreateContentType(['type' => 'test_content']);
    $this->setupField(
      'field_foobar',
      'node',
      'test_content',
      [
        'entity_type_ids' => ['user'],
        'exclude_entity_types' => FALSE,
      ],
      [
        'user' => [
          'handler' => 'default:user',
          'handler_settings' => [
            'target_bundles' => [
              'user' => 'user',
            ],
          ],
        ],
      ],
      'Foobar'
    );
    $this->drupalGet('admin/structure/types/manage/test_content/display');
    $page = $this->getSession()->getPage();
    $formats = $assert_session->selectExists('fields[field_foobar][type]', $page);
    $formats->selectOption('dynamic_entity_reference_entity_view');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Edit');
    $assert_session->assertWaitOnAjaxRequest();
    $page = $this->getSession()->getPage();
    $assert_session->selectExists('fields[field_foobar][settings_edit_form][settings][user][view_mode]', $page);
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][user][view_mode]', 'compact', $page);
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][user][view_mode]', 'full', $page);
    // Edit field, turn on exclude entity types and check display again.
    $storage = FieldStorageConfig::loadByName('node', 'field_foobar');
    $storage->setSetting('exclude_entity_types', TRUE)->save();
    $this->drupalGet('admin/structure/types/manage/test_content/display');
    $page = $this->getSession()->getPage();
    $formats = $assert_session->selectExists('fields[field_foobar][type]', $page);
    $formats->selectOption('dynamic_entity_reference_entity_view');
    $assert_session->assertWaitOnAjaxRequest();
    // Assert node view mode is set on default.
    $assert_session->responseContains("Content view mode: default");
    $page->pressButton('Edit');
    $assert_session->assertWaitOnAjaxRequest();
    $page = $this->getSession()->getPage();
    // Assert we have multi select form items for view mode settings.
    $assert_session->selectExists('fields[field_foobar][settings_edit_form][settings][entity_test_with_bundle][view_mode]', $page);
    $assert_session->responseContains("View mode for <em class=\"placeholder\">Test entity with bundle</em>");
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][entity_test_with_bundle][view_mode]', 'default', $page);
    $assert_session->optionNotExists('fields[field_foobar][settings_edit_form][settings][entity_test_with_bundle][view_mode]', 'rss', $page);
    $node_view_modes = $assert_session->selectExists('fields[field_foobar][settings_edit_form][settings][node][view_mode]', $page);
    $assert_session->responseContains("View mode for <em class=\"placeholder\">Content</em>");
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][node][view_mode]', 'default', $page);
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][node][view_mode]', 'full', $page);
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][node][view_mode]', 'rss', $page);
    $assert_session->optionExists('fields[field_foobar][settings_edit_form][settings][node][view_mode]', 'teaser', $page);
    // Select different select options and assert summary is changed properly.
    $node_view_modes->selectOption('teaser');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->responseContains("Content view mode: teaser");
    $page->pressButton('Edit');
    $assert_session->assertWaitOnAjaxRequest();
    $node_view_modes->selectOption('rss');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->responseContains("Content view mode: rss");
  }

  /**
   * Creates auto complete path for the given target type.
   *
   * @param string $target_type
   *   The entity type id.
   *
   * @return string
   *   Auto complete paths for the target type.
   */
  protected function createAutoCompletePath($target_type) {
    $selection_settings = [
      'target_bundles' => [$target_type => $target_type],
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
    ];
    $data = serialize($selection_settings) . $target_type . "default:$target_type";
    $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());
    return Url::fromRoute('system.entity_autocomplete', [
      'target_type' => $target_type,
      'selection_handler' => "default:$target_type",
      'selection_settings_key' => $selection_settings_key,
    ])->toString();
  }

  /**
   * Performs an autocomplete query on an element.
   *
   * @param string $autocomplete_query
   *   String to search for.
   * @param \Behat\Mink\Element\NodeElement $autocomplete_field
   *   Field to search in.
   */
  protected function performAutocompleteQuery($autocomplete_query, NodeElement $autocomplete_field) {
    $autocomplete_field->setValue($autocomplete_query);
    $autocomplete_field->keyDown(' ');
    $this->assertSession()->waitOnAutocomplete();
  }

  /**
   * Selects the autocomplete result with the given delta.
   *
   * @param int $delta
   *   Delta of item to select. Starts from 0.
   */
  protected function selectAutocompleteOption($delta = 0) {
    // Press the down arrow to select the nth option.
    /** @var \Behat\Mink\Element\NodeElement $element */
    $element = $this->getSession()->getPage()->findAll('css', '.ui-autocomplete.ui-menu li.ui-menu-item')[$delta];
    $element->click();
  }

}
