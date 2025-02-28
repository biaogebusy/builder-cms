<?php

namespace Drupal\Tests\panelizer\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Url;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;
use Drupal\Tests\BrowserTestBase;

/**
 * Basic functional tests of using Panelizer with nodes.
 *
 * @group panelizer
 */
class PanelizerNodeFunctionalTest extends BrowserTestBase {

  use PanelizerTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Modules for core functionality.
    'core_context_test',
    'node',
    'field_ui',
    'options',
    'user',
    'panels_ipe',
    'panelizer_test',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType(['type' => 'page']);

    BlockContentType::create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();
    block_content_add_body_field('test');

    $blocks = [
      'alpha',
      'beta',
      'charlie',
    ];
    foreach ($blocks as $block) {
      BlockContent::create([
        'type' => 'test',
        'info' => "$block title",
        'body' => "$block body",
        'uuid' => $block,
      ])->save();
    }

    /** @var \Drupal\panelizer\Panelizer $panelizer */
    $panelizer = $this->container->get('panelizer');

    // Enable all of Panelizer's functionality for the 'full' view mode,
    // including the ability to set up custom layouts per entity and choose from
    // non-default layouts in the view display.
    $panelizer->setPanelizerSettings('node', 'page', 'full', [
      'enable' => TRUE,
      'allow' => TRUE,
      'custom' => TRUE,
      'default' => 'default',
    ]);
    // Enable Panelizer for teasers, but don't allow per-entity customization
    // or non-default layout choices (yet).
    $panelizer->setPanelizerSettings('node', 'page', 'teaser', [
      'enable' => TRUE,
      'allow' => FALSE,
      'custom' => FALSE,
      'default' => 'default',
    ]);

    $panelizer->setDisplayStaticContexts('default', 'node', 'page', 'full', [
      'value' => [
        'type' => 'any',
        'label' => 'Lucky number',
        'description' => 'Always loop to this number and great things will happen',
        'value' => 42,
      ],
      'letter' => [
        'type' => 'string',
        'label' => 'Letter of the day',
        'description' => 'Straight from the NATO phonetic alphabet',
        'value' => 'Juliet',
      ],
    ]);

    $default = $panelizer->getDefaultPanelsDisplay('default', 'node', 'page', 'full');
    $default->addBlock([
      'id' => 'context_block_optional',
      'region' => 'content',
      'weight' => 0,
    ]);
    $panelizer->setDefaultPanelsDisplay('default', 'node', 'page', 'full', $default);

    // Clone the default display and save it with a new identifier so we can
    // test migration of non-default layouts.
    $alpha = clone $default;
    $configuration = $alpha->getConfiguration();
    $configuration['label'] = 'Alpha';
    $configuration['static_context'] = [
      'value' => [
        'type' => 'any',
        'label' => 'Lucky number',
        'description' => '100 with an off-by-one error',
        'value' => 99,
      ],
      'letter' => [
        'type' => 'string',
        'label' => 'Letter of the day',
        'description' => 'The coolest letter in existence',
        'value' => 'X-ray',
      ],
    ];
    $alpha->setConfiguration($configuration)->addBlock([
      'id' => 'system_powered_by_block',
      'region' => 'content',
      'weight' => 0,
    ]);
    $alpha->addBlock([
      'id' => 'block_content:alpha',
      'label' => 'alpha title',
      'region' => 'content',
      'weight' => 1,
    ]);
    $alpha->addBlock([
      'id' => 'context_block_optional',
      'region' => 'content',
      'weight' => 2,
    ]);
    $panelizer->setDefaultPanelsDisplay('alpha', 'node', 'page', 'full', $alpha);

    $teaser_display = $panelizer->getDefaultPanelsDisplay('default', 'node', 'page', 'teaser');
    $teaser_display->addBlock([
      'id' => 'block_content:alpha',
      'label' => 'alpha teaser',
      'region' => 'content',
      'weight' => 1,
    ]);
    $teaser_display->addBlock([
      'id' => 'context_block_optional',
      'region' => 'content',
      'weight' => 2,
    ]);
    $configuration = $teaser_display->getConfiguration();
    $configuration['static_context'] = [
      'value' => [
        'type' => 'any',
        'label' => 'Lucky number',
        'description' => 'A very good age to be',
        'value' => 35,
      ],
      'letter' => [
        'type' => 'string',
        'label' => 'Letter of the day',
        'description' => 'Ever dance with the devil in the pale moonlight?',
        'value' => 'Tango',
      ],
    ];
    $teaser_display->setConfiguration($configuration);
    $panelizer->setDefaultPanelsDisplay('default', 'node', 'page', 'teaser', $teaser_display);

    $this->loginUser1();
  }

  /**
   * Tests migration of the entity view display data to Layout Builder.
   */
  public function testMigrationToLayoutBuilder() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    /** @var \Drupal\panelizer\PanelizerInterface $panelizer */
    $panelizer = $this->container->get('panelizer');

    // Create a node to test with.
    $node = $this->drupalCreateNode(['type' => 'page']);

    // For the first revision, explicitly use whatever layout is the default for
    // the page node type.
    $panelizer->setPanelsDisplay($node, 'full', '__bundle_default__');

    // Get the revision URL so we can visit it later to ensure it was migrated.
    $alpha_revision_url = Url::fromRoute('entity.node.revision', [
      'node' => $node->id(),
      'node_revision' => $node->getRevisionId(),
    ]);

    /** @var \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant $panels_display */
    $panels_display = $panelizer->getPanelsDisplay($node, 'full');
    $this->assertInstanceOf(PanelsDisplayVariant::class, $panels_display);
    // Add the block to the custom layout.
    $beta_uuid = $panels_display->addBlock([
      'id' => 'block_content:beta',
      'label' => 'beta title',
      'region' => 'content',
      'weight' => 1,
    ]);
    $panelizer->setPanelsDisplay($node, 'full', NULL, $panels_display);

    // Get the revision URL so we can visit it later to ensure it was migrated.
    $beta_revision_url = Url::fromRoute('entity.node.revision', [
      'node' => $node->id(),
      'node_revision' => $node->getRevisionId(),
    ]);

    // Create a new revision with a different custom block in the layout.
    $panels_display->removeBlock($beta_uuid)->addBlock([
      'id' => 'block_content:charlie',
      'label' => 'charlie title',
      'region' => 'content',
      'weight' => 1,
    ]);
    $panelizer->setPanelsDisplay($node, 'full', NULL, $panels_display);

    // Create a query to count the number of revisions that are created during
    // the migration.
    /** @var \Drupal\Core\Entity\Query\QueryInterface $revision_count_query */
    $revision_count_query = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->getQuery()
      ->allRevisions()
      ->condition('nid', $node->id())
      ->accessCheck()
      ->count();
    $this->assertSame('4', $revision_count_query->execute());

    $this->drupalGet($node->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('charlie title');
    $assert_session->pageTextContains('charlie body');

    // Ensure the previous revisions look right.
    $this->drupalGet($beta_revision_url);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('beta title');
    $assert_session->pageTextContains('beta body');

    $this->drupalGet($alpha_revision_url);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('The context value is 42, brought to you by the letter Juliet.');

    $this->container->get('module_installer')->install(['layout_builder']);

    $this->drupalGet('/admin/structure/types/manage/page/display');
    $page->checkField('Full content');
    $page->checkField('Teaser');
    $page->pressButton('Save');

    $page->clickLink('Full content');
    $assert_session->checkboxChecked('Panelize this view mode');
    $assert_session->checkboxChecked('Allow users to select which display to use');
    $assert_session->checkboxChecked('Allow each content item to have its display customized');
    $assert_session->checkboxNotChecked('Use Layout Builder');
    $assert_session->checkboxNotChecked('Allow each content item to have its layout customized.');
    $page->pressButton('Migrate to Layout Builder');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Hold your horses, cowpoke.');
    $page->pressButton('I understand the risks and have backed up my database. Proceed!');
    $this->checkForMetaRefresh();
    $assert_session->checkboxChecked('Use Layout Builder');
    $assert_session->checkboxChecked('Allow content editors to use stored layouts');
    $assert_session->checkboxChecked('Allow each content item to have its layout customized.');
    $assert_session->fieldNotExists('Panelize this view mode');
    $assert_session->fieldNotExists('Allow users to select which display to use');
    $assert_session->fieldNotExists('Allow each content item to have its display customized');
    $page->clickLink('Manage layout');
    $page->pressButton('Save layout');

    $page->clickLink('Teaser');
    $assert_session->checkboxChecked('Panelize this view mode');
    $assert_session->checkboxNotChecked('Allow users to select which display to use');
    $assert_session->fieldNotExists('Allow each content item to have its display customized');
    $assert_session->checkboxNotChecked('Use Layout Builder');
    $assert_session->fieldNotExists('Allow each content item to have its layout customized.');
    $page->pressButton('Migrate to Layout Builder');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Hold your horses, cowpoke.');
    $page->pressButton('I understand the risks and have backed up my database. Proceed!');
    $this->checkForMetaRefresh();
    $assert_session->checkboxChecked('Use Layout Builder');
    $assert_session->checkboxNotChecked('Allow content editors to use stored layouts');
    $assert_session->fieldNotExists('Allow each content item to have its layout customized.');
    $assert_session->fieldNotExists('Panelize this view mode');
    $assert_session->fieldNotExists('Allow users to select which display to use');
    $assert_session->fieldNotExists('Allow each content item to have its display customized');
    $page->clickLink('Manage layout');
    $page->pressButton('Save layout');

    // No new revisions should have been created during the migration.
    $this->assertSame('4', $revision_count_query->execute());

    $this->drupalGet($node->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('charlie title');
    $assert_session->pageTextContains('charlie body');
    $assert_session->pageTextContains('The context value is 42, brought to you by the letter Juliet.');

    // Ensure the previous revisions look right.
    $this->drupalGet($beta_revision_url);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('beta title');
    $assert_session->pageTextContains('beta body');
    $assert_session->pageTextContains('The context value is 42, brought to you by the letter Juliet.');

    $this->drupalGet($alpha_revision_url);
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('The context value is 42, brought to you by the letter Juliet.');

    $node = $this->drupalCreateNode(['type' => 'page']);
    $this->drupalGet($node->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($node->getTitle());
    $assert_session->pageTextContains($node->body->value);
    $assert_session->pageTextNotContains('Powered by Drupal');
    $assert_session->pageTextNotContains('alpha title');
    $assert_session->pageTextNotContains('alpha body');
    $assert_session->pageTextContains('The context value is 42, brought to you by the letter Juliet.');

    $this->drupalGet($node->toUrl('edit-form'));
    $assert_session->statusCodeEquals(200);
    $page->selectFieldOption('Layout', 'Alpha');
    $page->pressButton('Save');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($node->getTitle());
    $assert_session->pageTextContains($node->body->value);
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->pageTextContains('alpha title');
    $assert_session->pageTextContains('alpha body');
    $assert_session->pageTextContains('The context value is 99, brought to you by the letter X-ray.');

    // Ensure that the teaser looks correct, too.
    $this->drupalGet('/node');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($node->getTitle());
    $assert_session->pageTextContains('alpha teaser');
    $assert_session->pageTextContains('alpha body');
    $assert_session->pageTextContains('The context value is 35, brought to you by the letter Tango.');
  }

  /**
   * Tests the admin interface to set a default layout for a bundle.
   */
  public function testWizardUI() {
    $assert_session = $this->assertSession();

    // Enter the wizard.
    $this->drupalGet('admin/structure/panelizer/edit/node__page__default__default');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Wizard Information');
    $this->assertSession()->fieldExists('edit-label');

    // Contexts step.
    $this->clickLink('Contexts');
    $this->assertSession()->pageTextContains('@panelizer.entity_context:entity');

    // Layout selection step.
    $this->clickLink('Layout');
    $this->assertSession()->buttonExists('edit-update-layout');

    // Content step. Add the Node block to the top region.
    $this->clickLink('Content');
    $this->clickLink('Add new block');
    $this->clickLink('Title');
    $edit = [
      'region' => 'content',
    ];
    $this->submitForm($edit, 'Add block');
    $this->assertSession()->statusCodeEquals(200);

    // Finish the wizard.
    $this->submitForm([], 'Update and save');
    $this->assertSession()->statusCodeEquals(200);
    // Confirm this returned to the main wizard page.
    $this->assertSession()->pageTextContains('Wizard Information');
    $this->assertSession()->fieldExists('edit-label');

    // Return to the Manage Display page, which is where the Cancel button
    // currently sends you. That's a UX WTF and should be fixed...
    $this->submitForm([], 'Cancel');
    $this->assertSession()->statusCodeEquals(200);

    // Confirm the page is back to the content type settings page.
    $this->assertSession()->checkboxChecked('edit-panelizer-custom');

    // Now change and save the general setting.
    $edit = [
      'panelizer[custom]' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldNotExists('panelizer[custom]');

    // Add another block at the Content step and then save changes.
    $this->drupalGet('admin/structure/panelizer/edit/node__page__default__default/content');
    $this->assertSession()->statusCodeEquals(200);
    $this->clickLink('Add new block');
    $this->clickLink('Body');
    $edit = [
      'region' => 'content',
    ];
    $this->submitForm($edit, 'Add block');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('entity_field:node:body');
    $this->submitForm([], 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->clickLink('Content');
    $this->assertSession()->pageTextContains('entity_field:node:body');

    // Check that the Manage Display tab changed now that Panelizer is set up.
    // Also, the field display table should be hidden.
    $this->assertSession()->responseNotContains('<div id="field-display-overview-wrapper">');

    // Disable Panelizer for the default display mode. This should bring back
    // the field overview table at Manage Display and not display the link to
    // edit the default Panelizer layout.
    $this->unpanelize('page');
    $this->assertSession()->linkByHrefNotExists('admin/structure/panelizer/edit/node__page__default');
    $this->assertSession()->responseContains('<div id="field-display-overview-wrapper">');
  }

  /**
   * Tests rendering a node with Panelizer default.
   */
  public function testPanelizerDefault() {
    /** @var \Drupal\panelizer\PanelizerInterface $panelizer */
    $panelizer = $this->container->get('panelizer');
    $displays = $panelizer->getDefaultPanelsDisplays('node', 'page', 'default');
    $display = $displays['default'];
    $display->addBlock([
      'id' => 'panelizer_test',
      'label' => 'Panelizer test',
      'provider' => 'block_content',
      'region' => 'content',
    ]);
    $panelizer->setDefaultPanelsDisplay('default', 'node', 'page', 'default', $display);

    // Create a node, and check that the IPE is visible on it.
    $node = $this->drupalCreateNode(['type' => 'page']);
    $out = $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
    $elements = $this->xpath('//*[@id="panels-ipe-content"]');
    $this->assertIsArray($elements, 'Could not parse page content.');
    $this->assertCount(1, $elements);

    // Check that the block we added is visible.
    $this->assertSession()->pageTextContains('Panelizer test');
    $this->assertSession()->pageTextContains('Abracadabra');
  }

}
