<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_content_suggestions\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_content_suggestions\AiContentSuggestionsFormAlter;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests the entity type filtering logic for AI content suggestions.
 *
 * @group ai_content_suggestions
 */
class EntityTypeFilterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'ai',
    'ai_content_suggestions',
    'node',
    'taxonomy',
    'block_content',
    'field',
    'text',
    'filter',
    'options',
  ];

  /**
   * The form alter service under test.
   */
  protected AiContentSuggestionsFormAlter $formAlter;

  /**
   * Node of type 'article'.
   */
  protected Node $nodeArticle;

  /**
   * Node of type 'page'.
   */
  protected Node $nodePage;

  /**
   * Taxonomy term in vocabulary 'tags'.
   */
  protected Term $termTags;

  /**
   * Taxonomy term in vocabulary 'categories'.
   */
  protected Term $termCategories;

  /**
   * Block content of type 'basic'.
   */
  protected BlockContent $blockBasic;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('block_content');
    $this->installSchema('node', ['node_access']);

    // Create 2 node content types.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    // Create 2 taxonomy vocabularies.
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    Vocabulary::create(['vid' => 'categories', 'name' => 'Categories'])->save();

    // Create 1 block content type.
    BlockContentType::create(['id' => 'basic', 'label' => 'Basic'])->save();

    // Unsaved entity instances — only entity type ID and bundle are needed.
    $this->nodeArticle = Node::create(['type' => 'article', 'title' => 'Article', 'uid' => 0]);
    $this->nodePage = Node::create(['type' => 'page', 'title' => 'Page', 'uid' => 0]);
    $this->termTags = Term::create(['vid' => 'tags', 'name' => 'Tag']);
    $this->termCategories = Term::create(['vid' => 'categories', 'name' => 'Category']);
    $this->blockBasic = BlockContent::create(['type' => 'basic', 'info' => 'Block']);

    $this->formAlter = $this->container->get('ai_content_suggestions.form_alter');

    // Initialise settings with all three entity types in disable/empty state.
    $this->container->get('config.factory')
      ->getEditable('ai_content_suggestions.settings')
      ->set('entity_types', [
        'node' => ['mode' => 'disable', 'bundles' => []],
        'taxonomy_term' => ['mode' => 'disable', 'bundles' => []],
        'block_content' => ['mode' => 'disable', 'bundles' => []],
      ])
      ->save();
  }

  /**
   * Sets entity type configuration for a single entity type.
   */
  protected function setEntityTypeConfig(string $entityType, string $mode, array $bundles): void {
    $config = $this->container->get('config.factory')
      ->getEditable('ai_content_suggestions.settings');
    $entityTypes = $config->get('entity_types') ?? [];
    $entityTypes[$entityType] = ['mode' => $mode, 'bundles' => $bundles];
    $config->set('entity_types', $entityTypes)->save();
  }

  /**
   * Tests disable mode with empty bundle list enables every bundle.
   */
  public function testDisableModeEmptyBundlesAllEnabled(): void {
    // Default state set in setUp: disable + no bundles → nothing is excluded.
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->nodeArticle));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->nodePage));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->termTags));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->termCategories));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->blockBasic));
  }

  /**
   * Tests disable mode excludes only the listed bundles.
   */
  public function testDisableModeExcludesListedBundles(): void {
    $this->setEntityTypeConfig('node', 'disable', ['page']);
    $this->setEntityTypeConfig('taxonomy_term', 'disable', ['tags']);
    // block_content keeps its default: disable + empty → all enabled.
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->nodePage));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->nodeArticle));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->termTags));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->termCategories));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->blockBasic));
  }

  /**
   * Tests enable mode includes only the listed bundles.
   */
  public function testEnableModeOnlyIncludesListedBundles(): void {
    $this->setEntityTypeConfig('node', 'enable', ['article']);
    $this->setEntityTypeConfig('taxonomy_term', 'enable', ['categories']);
    $this->setEntityTypeConfig('block_content', 'enable', ['basic']);

    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->nodeArticle));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->nodePage));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->termCategories));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->termTags));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->blockBasic));
  }

  /**
   * Tests enable mode with empty bundle list disables everything.
   */
  public function testEnableModeEmptyBundlesNothingEnabled(): void {
    $this->setEntityTypeConfig('node', 'enable', []);
    $this->setEntityTypeConfig('taxonomy_term', 'enable', []);
    $this->setEntityTypeConfig('block_content', 'enable', []);

    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->nodeArticle));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->nodePage));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->termTags));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->termCategories));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->blockBasic));
  }

  /**
   * Tests that an entity type absent from config is disabled.
   */
  public function testEntityTypeAbsentFromConfigIsDisabled(): void {
    $config = $this->container->get('config.factory')
      ->getEditable('ai_content_suggestions.settings');
    $entityTypes = $config->get('entity_types');
    unset($entityTypes['node']);
    $config->set('entity_types', $entityTypes)->save();

    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->nodeArticle));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->nodePage));
    // Taxonomy and block_content are still in config and still enabled.
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->termTags));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->blockBasic));
  }

  /**
   * Tests both node types are filtered independently.
   */
  public function testBothNodeTypesFilteredIndependently(): void {
    $this->setEntityTypeConfig('node', 'enable', ['article', 'page']);

    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->nodeArticle));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->nodePage));

    $this->setEntityTypeConfig('node', 'disable', ['article', 'page']);

    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->nodeArticle));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->nodePage));
  }

  /**
   * Tests both vocabulary terms are filtered independently.
   */
  public function testBothVocabulariesFilteredIndependently(): void {
    $this->setEntityTypeConfig('taxonomy_term', 'disable', ['tags']);

    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->termTags));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->termCategories));

    $this->setEntityTypeConfig('taxonomy_term', 'enable', ['categories']);

    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->termTags));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->termCategories));
  }

  /**
   * Tests mixed modes across entity types work independently.
   */
  public function testMixedModesAcrossEntityTypes(): void {
    // node: enable mode, only 'article' allowed.
    $this->setEntityTypeConfig('node', 'enable', ['article']);
    // taxonomy_term: disable mode, empty → all allowed.
    $this->setEntityTypeConfig('taxonomy_term', 'disable', []);
    // block_content: enable mode, empty → none allowed.
    $this->setEntityTypeConfig('block_content', 'enable', []);

    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->nodeArticle));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->nodePage));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->termTags));
    $this->assertTrue($this->formAlter->isEnabledForCurrentEntity($this->termCategories));
    $this->assertFalse($this->formAlter->isEnabledForCurrentEntity($this->blockBasic));
  }

}
