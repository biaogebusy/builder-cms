<?php

namespace Drupal\Tests\panelizer\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;
use Drupal\Tests\BrowserTestBase;

/**
 * Basic functional tests of using Panelizer with user entities.
 *
 * @group panelizer
 */
class PanelizerUserFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A custom block created for the test.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  private $blockContent;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'core_context_test',
    'field_ui',
    'options',
    'user',
    'panels_ipe',
    'panelizer_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('local_tasks_block');

    BlockContentType::create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();
    block_content_add_body_field('test');

    $this->blockContent = BlockContent::create([
      'type' => 'test',
      'info' => $this->randomString(),
      'body' => $this->getRandomGenerator()->sentences(5),
      'uuid' => 'test',
    ]);
    $this->blockContent->save();

    // Create the admin user.
    $user = $this->drupalCreateUser([
      // Required for Panelizer.
      'administer panelizer',
      'access panels in-place editing',
      // Allow managing user entities.
      'administer users',
      // Allow managing user entity settings.
      'administer account settings',
      // View access to user profiles.
      'access user profiles',
      // Allow managing the user entity fields and display settings.
      'administer user display',
      'administer user fields',
      'configure any layout',
    ]);
    $this->drupalLogin($user);

    /** @var \Drupal\panelizer\Panelizer $panelizer */
    $panelizer = $this->container->get('panelizer');

    $panelizer->setPanelizerSettings('user', 'user', 'full', [
      'enable' => TRUE,
      'allow' => TRUE,
      'custom' => TRUE,
      'default' => 'default',
    ]);

    $panelizer->setDisplayStaticContexts('default', 'user', 'user', 'full', [
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

    $default = $panelizer->getDefaultPanelsDisplay('default', 'user', 'user', 'full');
    $default->addBlock([
      'id' => 'context_block_optional',
      'region' => 'content',
      'weight' => 0,
    ]);
    $panelizer->setDefaultPanelsDisplay('default', 'user', 'user', 'full', $default);

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
      'id' => 'block_content:' . $this->blockContent->uuid(),
      'label' => $this->blockContent->label(),
      'region' => 'content',
      'weight' => 1,
    ]);
    $alpha->addBlock([
      'id' => 'context_block_optional',
      'region' => 'content',
      'weight' => 2,
    ]);
    $panelizer->setDefaultPanelsDisplay('alpha', 'user', 'user', 'full', $alpha);

    // Reload all caches.
    $this->rebuildAll();
  }

  /**
   * Tests migration of the entity view display data to Layout Builder.
   */
  public function testMigrationToLayoutBuilder() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Create a custom block to add to the custom layout.
    $block_content = BlockContent::create([
      'type' => 'test',
      'info' => $this->randomString(),
      'body' => $this->getRandomGenerator()->sentences(8),
    ]);
    $block_content->save();

    /** @var \Drupal\panelizer\PanelizerInterface $panelizer */
    $panelizer = $this->container->get('panelizer');

    // Create a user account that explicitly uses the default layout, to ensure
    // it does not break the migration.
    $default_layout_account = $this->drupalCreateUser();
    $panelizer->setPanelsDisplay($default_layout_account, 'full', '__bundle_default__');

    // Create a user account with a custom layout.
    $account = $this->drupalCreateUser();
    /** @var \Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant $panels_display */
    $panels_display = $panelizer->getPanelsDisplay($account, 'full');
    $this->assertInstanceOf(PanelsDisplayVariant::class, $panels_display);
    // Add the block to the custom layout.
    $panels_display->addBlock([
      'id' => 'block_content:' . $block_content->uuid(),
      'label' => $block_content->label(),
      'region' => 'content',
      'weight' => 1,
    ]);
    $panelizer->setPanelsDisplay($account, 'full', NULL, $panels_display);

    $this->drupalGet($default_layout_account->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('The context value is 42, brought to you by the letter Juliet.');

    $this->drupalGet($account->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($block_content->label());
    $assert_session->pageTextContains($block_content->body->value);

    $this->container->get('module_installer')->install(['layout_builder']);

    $this->drupalGet('/admin/config/people/accounts/display');
    $page->checkField('User account');

    $page->clickLink('User account');
    $assert_session->checkboxChecked('Panelize this view mode');
    $assert_session->checkboxChecked('Allow users to select which display to use');
    $assert_session->checkboxChecked('Allow each user to have its display customized');
    $assert_session->checkboxNotChecked('Use Layout Builder');
    $assert_session->checkboxNotChecked('Allow each user to have its layout customized.');
    $page->pressButton('Migrate to Layout Builder');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Hold your horses, cowpoke.');
    $page->pressButton('I understand the risks and have backed up my database. Proceed!');
    $this->checkForMetaRefresh();
    $assert_session->checkboxChecked('Use Layout Builder');
    $assert_session->checkboxChecked('Allow content editors to use stored layouts');
    $assert_session->checkboxChecked('Allow each user to have its layout customized.');
    $assert_session->fieldNotExists('Panelize this view mode');
    $assert_session->fieldNotExists('Allow users to select which display to use');
    $assert_session->fieldNotExists('Allow each user to have its display customized');
    $page->clickLink('Manage layout');
    $page->pressButton('Save layout');

    $this->drupalGet($default_layout_account->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('The context value is 42, brought to you by the letter Juliet.');

    $this->drupalGet($account->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($block_content->label());
    $assert_session->pageTextContains($block_content->body->value);
    $assert_session->pageTextContains('The context value is 42, brought to you by the letter Juliet.');

    $account = $this->createUser();
    $this->drupalGet($account->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($account->getDisplayName());
    $assert_session->pageTextNotContains('Powered by Drupal');
    $assert_session->pageTextNotContains($this->blockContent->label());
    $assert_session->pageTextNotContains($this->blockContent->body->value);
    $assert_session->pageTextContains('The context value is 42, brought to you by the letter Juliet.');

    $this->drupalGet($account->toUrl('edit-form'));
    $assert_session->statusCodeEquals(200);
    $page->selectFieldOption('Layout', 'Alpha');
    $page->pressButton('Save');
    $assert_session->statusCodeEquals(200);

    $this->drupalGet($account->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains($account->getDisplayName());
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->pageTextContains($this->blockContent->label());
    $assert_session->pageTextContains($this->blockContent->body->value);
    $assert_session->pageTextContains('The context value is 99, brought to you by the letter X-ray.');
  }

  /**
   * Tests rendering a user with Panelizer default.
   */
  public function testPanelizerDefault() {
    $assert_session = $this->assertSession();

    /** @var \Drupal\panelizer\PanelizerInterface $panelizer */
    $panelizer = \Drupal::service('panelizer');
    $displays = $panelizer->getDefaultPanelsDisplays('user', 'user', 'full');
    $display = $displays['default'];
    $display->addBlock([
      'id' => 'panelizer_test',
      'label' => 'Panelizer test',
      'provider' => 'block_content',
      'region' => 'content',
    ]);
    $panelizer->setDefaultPanelsDisplay('default', 'user', 'user', 'full', $display);

    // Create a user, and check that the IPE is visible on it.
    $account = $this->drupalCreateUser();

    // Check the user entity page.
    $out = $this->drupalGet($account->toUrl());
    $assert_session->statusCodeEquals(200);

    // Verify that Panels is rendering the page.
    $assert_session->elementsCount('css', '#panels-ipe-content', 1);

    // Check that the block we added is visible.
    $assert_session->pageTextContains('Panelizer test');
    $assert_session->pageTextContains('Abracadabra');
  }

}
