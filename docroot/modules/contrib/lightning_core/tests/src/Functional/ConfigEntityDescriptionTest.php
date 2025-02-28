<?php

namespace Drupal\Tests\lightning_core\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group lightning_core
 */
class ConfigEntityDescriptionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'field_ui',
    'help',
    'lightning_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('help_block');
    $this->createContentType(['type' => 'page']);
  }

  public function testRole() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $account = $this->drupalCreateUser([
      'access administration pages',
      'administer users',
      'administer permissions',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet("/admin/people/roles/add");
    $assert_session->statusCodeEquals(200);
    $page->fillField('Role name', 'Foobaz');
    $page->fillField('id', 'foobaz');
    $page->fillField('Description', 'I am godd here');
    $page->pressButton('Save');

    $this->drupalGet('/user');
    $assert_session->statusCodeEquals(200);
    $page->clickLink('Edit');
    $page->pressButton('Save');
    $assert_session->pageTextContains('I am godd here');
  }

  public function testViewMode() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $account = $this->drupalCreateUser([
      'administer display modes',
      'administer node display',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('/admin/structure/display-modes/view');
    $assert_session->statusCodeEquals(200);
    $page->clickLink('Add new Content view mode');
    $page->fillField('Name', 'Foobaz');
    $page->fillField('id', 'foobaz');
    $page->fillField('Description', 'Behold my glorious view mode.');
    $page->pressButton('Save');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Foobaz');

    $this->drupalGet("/admin/structure/types/manage/page/display");
    $assert_session->statusCodeEquals(200);
    $page->checkField('Foobaz');
    $page->pressButton('Save');
    $page->clickLink('Foobaz');
    $assert_session->pageTextContains('Behold my glorious view mode.');
  }

  public function testFormMode() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $account = $this->drupalCreateUser([
      'administer display modes',
      'administer node form display',
    ]);
    $this->drupalLogin($account);

    $this->drupalGet('/admin/structure/display-modes/form');
    $assert_session->statusCodeEquals(200);
    $page->clickLink('Add form mode');
    $page->clickLink('Content');
    $page->fillField('Name', 'Foobaz');
    $page->fillField('id', 'foobaz');
    $page->fillField('Description', 'Behold my glorious form mode.');
    $page->pressButton('Save');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Foobaz');

    $this->drupalGet("/admin/structure/types/manage/page/form-display");
    $assert_session->statusCodeEquals(200);
    $page->checkField('Foobaz');
    $page->pressButton('Save');
    $page->clickLink('Foobaz');
    $assert_session->pageTextContains('Behold my glorious form mode.');
  }

}
