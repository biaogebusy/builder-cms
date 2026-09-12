<?php

namespace Drupal\Tests\webform\Functional\Access;

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\filter\Entity\FilterFormat;
use Drupal\webform\Element\WebformHtmlEditor;

/**
 * Tests for webform default filter format access.
 *
 * @group webform
 */
class WebformAccessFilterFormatTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'field_ui', 'webform'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_html_editor'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create filters.
    $this->createFilters();
  }

  /**
   * Tests webform default filter format access.
   */
  public function testFilterFormatAccess(): void {
    $assert_session = $this->assertSession();

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    // Check that the default filter format is installed.
    $webform_filter_format = FilterFormat::load(WebformHtmlEditor::DEFAULT_FILTER_FORMAT);
    $this->assertNotNull($webform_filter_format);

    /* ********************************************************************** */
    // Check filter format access.
    /* ********************************************************************** */

    // Check that a root user can't update or disable
    // the webform default filter format.
    $account_switcher->switchTo($this->rootUser);
    $this->assertFalse($webform_filter_format->access('update'));
    $this->assertFalse($webform_filter_format->access('disable'));
    // Check that a root user can use the webform default filter format.
    $this->assertTrue($webform_filter_format->access('use'));
    $account_switcher->switchBack();

    // Check that a filter admin user can't update or disable
    // the webform default filter format.
    $filter_admin = $this->createUser(['administer filters']);
    $account_switcher->switchTo($filter_admin);
    $this->assertFalse($webform_filter_format->access('update'));
    $this->assertFalse($webform_filter_format->access('disable'));
    // Check that a root user can use the webform default filter format.
    $this->assertTrue($webform_filter_format->access('use'));
    $account_switcher->switchBack();

    /* ********************************************************************** */
    // Check HTML editor access.
    /* ********************************************************************** */

    // Check that authenticated users can use the webform default text format.
    $this->drupalLogin($this->createUser());
    $this->drupalGet('/webform/test_element_html_editor');
    $assert_session->fieldValueNotEquals('webform_html_editor[value][value]', 'This field has been disabled because you do not have sufficient permissions to edit it.');

    // Check that anonymous users can use the webform default text format.
    $this->drupalLogout();
    $this->drupalGet('/webform/test_element_html_editor');
    $assert_session->fieldValueEquals('webform_html_editor[value][value]', 'This field has been disabled because you do not have sufficient permissions to edit it.');

    /* ********************************************************************** */
    // Check UI access.
    /* ********************************************************************** */

    // Login as a root user.
    $this->drupalLogin($this->rootUser);

    // Check that webform default filter format is not included on
    // text format list page.
    $this->drupalGet('/admin/config/content/formats');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseNotContains(WebformHtmlEditor::DEFAULT_FILTER_FORMAT);

    // Check that webform default filter format is not included on
    // permissions pages.
    $this->drupalGet('/admin/people/permissions');
    $assert_session->responseNotContains(WebformHtmlEditor::DEFAULT_FILTER_FORMAT);
    $this->drupalGet('/admin/people/permissions/authenticated');
    $assert_session->responseNotContains(WebformHtmlEditor::DEFAULT_FILTER_FORMAT);

    // Check that editing the webform default format is blocked.
    $this->drupalGet('/admin/config/content/formats/manage/webform_default');
    $assert_session->statusCodeEquals(403);

    // Check that disabling the webform default format is blocked.
    $this->drupalGet('/admin/config/content/formats/manage/webform_default/disable');
    $assert_session->statusCodeEquals(403);

    /* ********************************************************************** */
    // Check markup/process text test.
    // @see check_markup().
    // @see \Drupal\webform\Element\WebformHtmlEditor::preRenderText
    /* ********************************************************************** */

    // Check webform default format is NOT accessible via check_markup().
    // @see \Drupal\webform\Element\WebformHtmlEditor::preRenderText
    $this->assertEquals('', check_markup('<script></script>Test', WebformHtmlEditor::DEFAULT_FILTER_FORMAT));

    /* ********************************************************************** */
    // Check Text format access.
    /* ********************************************************************** */

    // Check that a user won't see the text format select menu
    // when they only have the default or one filter visible.
    // @covers \Drupal\webform\Element\WebformHtmlEditor::processTextFormat
    $this->drupalLogout();
    $this->drupalLogin(
      $this->drupalCreateUser([
        'administer nodes',
        'bypass node access',
      ])
    );
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
    $node = $this->drupalCreateNode();
    $this->drupalGet('/node/' . $node->id() . '/edit');
    $assert_session->elementNotExists('css', '[name="body[0][format]"]');
    $assert_session->responseNotContains('<option value="plain_text" selected="selected">Plain text</option><');

    /* ********************************************************************** */
    // Check config settings allowed formats test.
    // @see \Drupal\text\Plugin\Field\FieldType\TextItemBase::fieldSettingsForm
    /* ********************************************************************** */

    // Check removing the 'Webform (Default) - DO NOT EDIT' options
    // from allowed formats.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/admin/structure/types/manage/page/fields/node.page.body');
    $assert_session->responseNotContains(WebformHtmlEditor::DEFAULT_FILTER_FORMAT);
  }

}
