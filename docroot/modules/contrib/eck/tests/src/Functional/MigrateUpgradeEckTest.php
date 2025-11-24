<?php

namespace Drupal\Tests\eck\Functional;

use Drupal\Tests\migrate_drupal_ui\Functional\MigrateUpgradeTestBase;

/**
 * Tests ECK upgrade using the migrate UI.
 *
 * The test method is provided by the MigrateUpgradeTestBase class.
 *
 * @group eck
 */
class MigrateUpgradeEckTest extends MigrateUpgradeTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'eck',
    'node',
    'content_translation',
    'field',
    'language',
    'migrate',
    'migrate_drupal',
    'migrate_drupal_ui',
    'text',
  ];

  /**
   * Input data for the credential form.
   *
   * @var array
   */
  protected $edits;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->writeSettings([
      'settings' => [
        'migrate_node_migrate_type_classic' => (object) [
          'value' => TRUE,
          'required' => TRUE,
        ],
      ],
    ]);
    $this->loadFixture(\Drupal::service('extension.list.module')->getPath('eck') . '/tests/fixtures/drupal7.php');
  }

  /**
   * Tests the migrate upgrade review form and upgrade process.
   */
  public function testMigrateUpgrade() {
    $this->submitCredentialForm();
    $this->submitForm([], 'I acknowledge I may lose data. Continue anyway.');
    $this->assertSession()->statusCodeEquals(200);

    // Test the upgrade paths.
    $this->assertReviewForm();
    $this->submitForm([], 'Perform upgrade');
    $this->assertUpgrade($this->getEntityCounts());
  }

  /**
   * Navigates to the credential form and submits valid credentials.
   */
  public function submitCredentialForm() {
    $this->drupalGet('/upgrade');
    $this->submitForm([], 'Continue');

    // Get valid credentials.
    $this->edits = $this->translatePostValues($this->getCredentials());
    // When the Credential form is submitted the migrate map tables are created.
    $this->submitForm($this->edits, 'Review upgrade');
  }

  /**
   * Creates an array of credentials for the Credential form.
   *
   * Before submitting to the Credential form the array must be processed by
   * BrowserTestBase::translatePostValues() before submitting.
   *
   * @return array
   *   An array of values suitable for BrowserTestBase::translatePostValues().
   *
   * @see \Drupal\migrate_drupal_ui\Form\CredentialForm
   */
  protected function getCredentials() {
    $connection_options = $this->sourceDatabase->getConnectionOptions();
    $version = $this->getLegacyDrupalVersion($this->sourceDatabase);
    $driver = $connection_options['driver'];

    // Use the driver connection form to get the correct options out of the
    // database settings. This supports all of the databases we test against.
    $drivers = drupal_get_database_types();
    $form = $drivers[$driver]->getFormOptions($connection_options);
    $connection_options = array_intersect_key($connection_options, $form + $form['advanced_options']);
    $edit = [
      $driver => $connection_options,
      'source_private_file_path' => $this->getSourceBasePath(),
      'version' => $version,
    ];
    if ($version == 6) {
      $edit['d6_source_base_path'] = $this->getSourceBasePath();
    }
    else {
      $edit['source_base_path'] = $this->getSourceBasePath();
      $edit['source_private_file_path'] = $this->getSourcePrivateBasePath();
    }
    if (\count($drivers) !== 1) {
      $edit['driver'] = $driver;
    }
    return $edit;
  }

  /**
   * Asserts the upgrade completed successfully.
   *
   * @param array $entity_counts
   *   An array of entity count, where the key is the entity type and the value
   *   is the number of the entities that should exist post migration.
   */
  protected function assertUpgrade(array $entity_counts) {
    // Assert the count of entities after the upgrade. First, reset all the
    // statics after migration to ensure entities are loadable.
    $this->resetAll();
    // Check that the expected number of entities is the same as the actual
    // number of entities.
    $entity_definitions = array_keys(\Drupal::entityTypeManager()->getDefinitions());

    // Ignore some entity types because the counts are different in different
    // core versions.
    $ignored_entity_types = ['tour', 'image_style'];
    $entity_definitions = array_diff($entity_definitions, $ignored_entity_types);

    ksort($entity_counts);
    $expected_count_keys = array_keys($entity_counts);
    sort($entity_definitions);
    $this->assertSame($expected_count_keys, $entity_definitions);

    // Assert the correct number of entities exists.
    $actual_entity_counts = [];
    foreach ($entity_definitions as $entity_type) {
      $actual_entity_counts[$entity_type] = (int) \Drupal::entityQuery($entity_type)->accessCheck(FALSE)->count()->execute();
    }
    $this->assertSame($entity_counts, $actual_entity_counts);
  }

  /**
   * Helper to assert content on the Review form.
   *
   * @param array|null $available_paths
   *   An array of modules that will be upgraded. Defaults to
   *   $this->getAvailablePaths().
   * @param array|null $missing_paths
   *   An array of modules that will not be upgraded. Defaults to
   *   $this->getMissingPaths().
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertReviewForm(array $available_paths = NULL, array $missing_paths = NULL) {
    $session = $this->assertSession();
    $session->pageTextContains('What will be upgraded?');

    $available_paths = $available_paths ?? $this->getAvailablePaths();
    $missing_paths = $missing_paths ?? $this->getMissingPaths();
    // Test the available migration paths.
    foreach ($available_paths as $machine_name => $label) {
      $session->elementExists('xpath', $this->getReviewFormXpath("[contains(@class, 'checked') and (text() = '$machine_name' or text() = '$label')]"));
      $session->elementNotExists('xpath', $this->getReviewFormXpath("[contains(@class, 'error') and (text() = '$machine_name' or text() = '$label')]"));
    }

    // Test the missing migration paths.
    foreach ($missing_paths as $machine_name => $label) {
      $session->elementExists('xpath', $this->getReviewFormXpath("[contains(@class, 'error') and (text() = '$machine_name' or text() = '$label')]"));
      $session->elementNotExists('xpath', $this->getReviewFormXpath("[contains(@class, 'checked') and (text() = '$machine_name' or text() = '$label')]"));
    }

    // Test the total count of missing and available paths.
    $session->elementsCount('xpath', $this->getReviewFormXpath("[contains(@class, 'upgrade-analysis-report__status-icon--error')]"), \count($missing_paths));
    $session->elementsCount('xpath', $this->getReviewFormXpath("[contains(@class, 'upgrade-analysis-report__status-icon--checked')]"), \count($available_paths));
  }

  /**
   * {@inheritdoc}
   */
  protected function getSourceBasePath() {
    return __DIR__ . '/files';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityCounts() {
    return [
      'action' => 21,
      'base_field_override' => 1,
      'block' => 24,
      'block_content' => 0,
      'block_content_type' => 1,
      'comment' => 0,
      'comment_type' => 2,
      'complex_entity' => 5,
      'complex_entity_type' => 2,
      'configurable_language' => 4,
      'contact_form' => 2,
      'contact_message' => 0,
      'date_format' => 12,
      'eck_entity_bundle' => 0,
      'eck_entity_type' => 2,
      'editor' => 2,
      'entity_form_display' => 9,
      'entity_form_mode' => 1,
      'entity_view_display' => 13,
      'entity_view_mode' => 12,
      'field_config' => 17,
      'field_storage_config' => 14,
      'file' => 1,
      'filter_format' => 4,
      'language_content_settings' => 4,
      'menu' => 5,
      'menu_link_content' => 0,
      'node' => 2,
      'node_type' => 2,
      'path_alias' => 0,
      'search_page' => 2,
      'shortcut' => 2,
      'shortcut_set' => 1,
      'simple_entity' => 2,
      'simple_entity_type' => 1,
      'taxonomy_term' => 0,
      'taxonomy_vocabulary' => 1,
      'user' => 2,
      'user_role' => 4,
      'view' => 14,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAvailablePaths() {
    return [
      'block' => 'Block',
      'comment' => 'Comment',
      'ctools' => 'Chaos tools',
      'dblog' => 'Database logging',
      'eck' => 'Entity Construction Kit',
      'entity' => 'Entity API',
      'entityreference' => 'Entity Reference',
      'entity_translation' => 'Entity Translation',
      'field' => 'Field',
      'field_sql_storage' => 'Field SQL storage',
      'field_ui' => 'Field UI',
      'file' => 'File',
      'filter' => 'Filter',
      'locale' => 'Locale',
      'node' => 'Node',
      'system' => 'System',
      'text' => 'Text',
      'user' => 'User',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getMissingPaths() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityCountsIncremental() {}

  /**
   * Provides the source base path for private files for the credential form.
   *
   * @return string|null
   *   The source base path.
   */
  protected function getSourcePrivateBasePath() {
    return NULL;
  }

  /**
   * Prepares xpath for review forms.
   *
   * Builds xpath to catch two possible tags (td and span) to support different
   * versions of Drupal core.
   */
  protected function getReviewFormXpath($common_xpath) {
    $parts = [];
    foreach (['span', 'td'] as $tag) {
      $parts[] = "//{$tag}{$common_xpath}";
    }
    return implode('|', $parts);
  }

}
