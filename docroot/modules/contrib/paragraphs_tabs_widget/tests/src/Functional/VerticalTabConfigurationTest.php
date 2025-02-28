<?php

namespace Drupal\Tests\paragraphs_tabs_widget\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\Tests\paragraphs_tabs_widget\Traits\VerticalTabsTestTrait;

/**
 * Test that the widget configuration can be saved.
 *
 * @group paragraphs_tabs_widget
 */
class VerticalTabConfigurationTest extends BrowserTestBase {
  use ParagraphsTestBaseTrait;
  use VerticalTabsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'node',
    'paragraphs',
    'paragraphs_tabs_widget',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $sutAdminUser;

  /**
   * A user without administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $sutNonAdminUser;

  /**
   * An xpath query for the settings button on the manage form display page.
   *
   * @var string
   */
  protected $xpathQuerySettingsButton;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpRandomParagraphTypeWithRandomTextField();
    $this->setUpRandomNodeTypeWithRandomParagraphRefField();

    // Create a user that can create nodes.
    $this->sutAdminUser = $this->createUser([
      'administer node form display',
      'change paragraphs_tabs_widget summary_selector',
    ]);
    $this->sutNonAdminUser = $this->createUser(['administer node form display']);

    $this->xpathQuerySettingsButton = $this->assertSession()->buildXPathQuery('//input[@name=:settingsName]', [
      ':settingsName' => $this->sutParagraphNodeFieldName . '_settings_edit',
    ]);
  }

  /**
   * Check that unprivileged users cannot set the tax summary selector.
   */
  public function testUserWithoutPermissionCannotAccessTabSummarySelector() {
    $page = $this->getSession()->getPage();
    $nodeId = $this->sutNodeType->id();

    // Log in as the user with permissions for this test.
    $this->drupalLogin($this->sutNonAdminUser);
    $this->drupalGet("/admin/structure/types/manage/{$nodeId}/form-display");
    $this->assertSession()->statusCodeEquals(200);

    // Click the "settings" button for the field.
    $settingsButton = $page->find('xpath', $this->xpathQuerySettingsButton);
    $this->assertNotNull($settingsButton, 'Could not find settings button.');
    $settingsButton->click();

    // Check the field is not visible.
    $this->assertSession()->fieldNotExists("fields[{$this->sutParagraphNodeFieldName}][settings_edit_form][settings][summary_selector]");
  }

  /**
   * Check that privileged users can set the tax summary selector.
   */
  public function testUserWithPermissionCanAccessTabSummarySelector() {
    $page = $this->getSession()->getPage();
    $nodeId = $this->sutNodeType->id();

    // Log in as the user with permissions for this test.
    $this->drupalLogin($this->sutAdminUser);
    $this->drupalGet("/admin/structure/types/manage/{$nodeId}/form-display");
    $this->assertSession()->statusCodeEquals(200);

    // Click the "settings" button for the field.
    $settingsButton = $page->find('xpath', $this->xpathQuerySettingsButton);
    $this->assertNotNull($settingsButton, 'Could not find settings button.');
    $settingsButton->click();

    // Check the field is visible.
    $this->assertSession()->fieldExists("fields[{$this->sutParagraphNodeFieldName}][settings_edit_form][settings][summary_selector]");
  }

}
