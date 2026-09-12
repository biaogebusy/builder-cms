<?php

namespace Drupal\Tests\pathauto\FunctionalJavascript;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\pathauto\PathautoState;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test pathauto functionality with localization and translation.
 *
 * @group pathauto
 */
#[Group('pathauto')]
#[RunTestsInSeparateProcesses]
class PathautoLocaleTest extends WebDriverTestBase {

  use PathautoTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'pathauto', 'locale', 'content_translation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create Article node type.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
  }

  /**
   * Test that English alias updates don't affect French alias.
   *
   * Test that when an English node is updated, its old English alias is
   * updated and its newer French alias is left intact.
   */
  public function testLanguageAliases(): void {

    $this->createPattern('node', '/content/[node:title]');

    // Add predefined French language.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $node = [
      'title' => 'English node',
      'langcode' => 'en',
      'path' => [
        [
          'alias' => '/english-node',
          'pathauto' => FALSE,
        ],
      ],
    ];
    $node = $this->drupalCreateNode($node);
    $english_alias = $this->loadPathAliasByConditions(['alias' => '/english-node', 'langcode' => 'en']);
    $this->assertNotEmpty($english_alias, 'Alias created with proper language.');

    // Also save a French alias that should not be left alone, even though
    // it is the newer alias.
    $this->saveEntityAlias($node, '/french-node', 'fr');

    // Add an alias with the soon-to-be generated alias, causing the upcoming
    // alias update to generate a unique alias with the '-0' suffix.
    $this->createPathAlias('/node/invalid', '/content/english-node', Language::LANGCODE_NOT_SPECIFIED);

    // Update the node, triggering a change in the English alias.
    $node->path->pathauto = PathautoState::CREATE;
    $node->save();

    // Check that the new English alias replaced the old one.
    $this->assertEntityAlias($node, '/content/english-node-0', 'en');
    $this->assertEntityAlias($node, '/french-node', 'fr');
    $this->assertAliasExists(['id' => $english_alias->id(), 'alias' => '/content/english-node-0']);

    // Create a new node with the same title as before but without
    // specifying a language.
    $node = $this->drupalCreateNode(['title' => 'English node', 'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED]);

    // Check that the new node had a unique alias generated with the '-0'
    // suffix.
    $this->assertEntityAlias($node, '/content/english-node-0', LanguageInterface::LANGCODE_NOT_SPECIFIED);
  }

  /**
   * Test that patterns work on multilingual content.
   */
  public function testLanguagePatterns(): void {

    // Allow other modules to add additional permissions for the admin user.
    $permissions = [
      'administer pathauto',
      'administer url aliases',
      'bulk delete aliases',
      'bulk update aliases',
      'create url aliases',
      'bypass node access',
      'access content overview',
      'administer languages',
      'translate any entity',
      'administer content translation',
      'create content translations',
    ];
    $admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin_user);

    // Add predefined French language.
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->enableArticleTranslation();

    // Create a pattern for English articles.
    $this->addPathautoPattern('English articles', 'en', '/the-articles/[node:title]');

    // Create a pattern for French articles.
    $this->addPathautoPattern('French articles', 'fr', '/les-articles/[node:title]');

    // Create a node and its translation. Assert aliases.
    $edit = [
      'title[0][value]' => 'English node',
      'langcode[0][value]' => 'en',
    ];
    $this->drupalGet('node/add/article');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->waitForText('English node has been created.');
    $node = $this->drupalGetNodeByTitle('English node');
    $this->assertAlias('/node/' . $node->id(), '/the-articles/english-node', 'en');

    $this->drupalGet('node/' . $node->id() . '/translations');
    $this->clickLink('Add');
    $edit = [
      'title[0][value]' => 'French node',
    ];
    $this->submitForm($edit, 'Save (this translation)');
    $this->rebuildContainer();
    $this->assertAlias('/node/' . $node->id(), '/les-articles/french-node', 'fr');

    // Bulk delete and Bulk generate patterns. Assert aliases.
    $this->deleteAllAliases();
    // Bulk create aliases.
    $edit = [
      'update[canonical_entities:node]' => TRUE,
    ];
    $this->drupalGet('admin/config/search/path/update_bulk');
    $this->submitForm($edit, 'Update');
    $this->assertTrue($this->assertSession()->waitForText('Generated 2 URL aliases.'));
    $this->assertAlias('/node/' . $node->id(), '/the-articles/english-node', 'en');
    $this->assertAlias('/node/' . $node->id(), '/les-articles/french-node', 'fr');
  }

  /**
   * Tests the alias created for a node with language Not Applicable.
   */
  public function testLanguageNotApplicable(): void {
    $this->drupalLogin($this->rootUser);
    $this->enableArticleTranslation();

    // Create a pattern for nodes.
    $pattern = $this->createPattern('node', '/content/[node:title]', -1);
    $pattern->save();

    // Create a node with language Not Applicable.
    $node = $this->createNode([
      'type' => 'article',
      'title' => 'Test node',
      'langcode' => LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ]);

    // Check that the generated alias has language Not Specified.
    $alias = \Drupal::service('pathauto.alias_storage_helper')->loadBySource('/node/' . $node->id());
    $this->assertEquals(LanguageInterface::LANGCODE_NOT_SPECIFIED, $alias['langcode'], 'PathautoGenerator::createEntityAlias() adjusts the alias langcode from Not Applicable to Not Specified.');

    // Check that the alias works.
    $this->drupalGet('content/test-node');
    $this->assertSession()->pageTextContains('Test node');
  }

  /**
   * Creates a pathauto pattern for article nodes via the UI.
   *
   * @param string $label
   *   The pattern label.
   * @param string $langcode
   *   The language code to restrict the pattern to.
   * @param string $pattern
   *   The path pattern string.
   */
  protected function addPathautoPattern(string $label, string $langcode, string $pattern): void {
    $this->drupalGet('admin/config/search/path/patterns/add');
    $page = $this->getSession()->getPage();

    $page->selectFieldOption('type', 'canonical_entities:node');
    $this->assertSession()->assertExpectedAjaxRequest(1);

    $edit = [
      'label' => $label,
      'pattern' => $pattern,
      'bundles[article]' => TRUE,
      'languages[' . $langcode . ']' => TRUE,
    ];
    $this->submitForm($edit, 'Save');

    $this->assertTrue($this->assertSession()->waitForText('Pattern ' . $label . ' saved.'));
  }

  /**
   * Tests no false "alias already in use" error when translating content.
   *
   * When two nodes in different languages share the same alias (which is
   * allowed), adding a translation of one can trigger a false "alias already
   * in use" validation error. The translation form inherits the source alias,
   * and the validator finds the other node's alias as a conflict (same alias +
   * same target langcode + different path). PathautoWidget should skip
   * validation when automatic aliasing is enabled, since pathauto will
   * regenerate and uniquify the alias on save.
   *
   * @see https://www.drupal.org/i/3267989
   */
  public function testTranslationNoFalseAliasConflict() {
    $permissions = [
      'administer pathauto',
      'administer url aliases',
      'create url aliases',
      'bypass node access',
      'administer languages',
      'translate any entity',
      'administer content translation',
      'create content translations',
    ];
    $admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin_user);

    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->enableArticleTranslation();
    $this->createPattern('node', '/content/[node:title]');

    // Create an English node programmatically. Pathauto generates alias
    // /content/test-node for langcode 'en'.
    $node_en = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test node',
      'langcode' => 'en',
    ]);
    $this->assertEntityAlias($node_en, '/content/test-node', 'en');

    // Create a French node with the same alias. Same alias, different
    // language: this is allowed by the unique alias constraint.
    $node_fr = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Test node',
      'langcode' => 'fr',
      'path' => [
        [
          'alias' => '/content/test-node',
          'pathauto' => FALSE,
        ],
      ],
    ]);
    $this->assertEntityAlias($node_fr, '/content/test-node', 'fr');

    // Now add a French translation of the English node via the form. The
    // translation form inherits the source alias /content/test-node. The
    // validator would find the French node's alias as a conflict (same alias
    // + langcode 'fr' + different path). With pathauto enabled, this should
    // not trigger a validation error because pathauto will regenerate and
    // uniquify the alias on save.
    $this->drupalGet('node/' . $node_en->id() . '/translations/add/en/fr');
    $this->submitForm([], 'Save (this translation)');
    $this->assertSession()->pageTextContains('Article Test node has been updated.');
    $this->assertSession()->pageTextNotContains('is already in use in this language');

    // Verify pathauto generated a uniquified alias for the translation.
    $this->assertEntityAlias($node_en, '/content/test-node-0', 'fr');
  }

  /**
   * Enables content translation on articles.
   */
  protected function enableArticleTranslation(): void {
    // Enable content translation on articles.
    $this->drupalGet('admin/config/regional/content-language');

    // Enable translation for node.
    $this->assertSession()->fieldExists('entity_types[node]')->check();
    $this->assertSession()->waitForElementVisible('css', '#edit-settings-node');
    // Open details for Content settings in Drupal 10.2.
    $nodeSettings = $this->getSession()->getPage()->find('css', '#edit-settings-node summary');
    if ($nodeSettings) {
      $nodeSettings->click();
    }
    $this->assertSession()->fieldExists('settings[node][article][translatable]')->check();
    $this->assertSession()->fieldExists('settings[node][article][settings][language][language_alterable]')->check();

    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertTrue($this->assertSession()->waitForText('Settings successfully updated.'));
  }

}
