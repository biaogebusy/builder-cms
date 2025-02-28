<?php

namespace Drupal\Tests\paragraphs_tabs_widget\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\Tests\paragraphs_tabs_widget\Traits\VerticalTabsTestTrait;

/**
 * Test the vertical tab widget displays correctly, and AHAH works as expected.
 *
 * @group paragraphs_tabs_widget
 */
class VerticalTabWidgetJsTest extends WebDriverTestBase {
  use ParagraphsTestBaseTrait;
  use VerticalTabsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'paragraphs',
    'paragraphs_tabs_widget',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An editable node for the system under test.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $sutNode;

  /**
   * A user with administrative privileges for the system under test.
   *
   * @var \Drupal\user\Entity\User
   */
  private $sutAdminUser;

  /**
   * A random value for the first paragraph on the "edit" test fixture.
   *
   * @var string
   */
  private $sutParagraph1RandomValue;

  /**
   * A random value for the second paragraph on the "edit" test fixture.
   *
   * @var string
   */
  private $sutParagraph2RandomValue;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpRandomParagraphTypeWithRandomTextField();
    $this->setUpRandomNodeTypeWithRandomParagraphRefField();

    // Create a node we can edit.
    $this->sutParagraph1RandomValue = $this->getRandomGenerator()->string();
    $this->sutParagraph2RandomValue = $this->getRandomGenerator()->string();
    $this->sutNode = $this->drupalCreateNode([
      'type' => $this->sutNodeType->id(),
      'title' => $this->getRandomGenerator()->string(22),
      $this->sutParagraphNodeFieldName => [
        $this->drupalCreateParagraph([
          'type' => $this->sutParagraphTypeName,
          $this->sutParagraphParagraphFieldName => $this->sutParagraph1RandomValue,
        ]),
        $this->drupalCreateParagraph([
          'type' => $this->sutParagraphTypeName,
          $this->sutParagraphParagraphFieldName => $this->sutParagraph2RandomValue,
        ]),
      ],
    ]);

    // Create a user that can create nodes.
    $this->sutAdminUser = $this->createUser(['administer nodes', 'bypass node access']);
  }

  /**
   * Test vertical_tabs library moves "Add more" button to tab menu on add.
   */
  public function testParagraphAddButtonInTabMenuAddPage() {
    $this->drupalLogin($this->sutAdminUser);

    // Check the "Add more" button on a node/add page (i.e.: empty content) by
    // verifying we can find an add more button is inside the vertical tabs menu
    // list.
    $this->drupalGet(Url::fromRoute('node.add', ['node_type' => $this->sutNodeType->id()])->toString());
    $page = $this->getSession()->getPage();
    $vtAddButton = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[contains(@class,:jsVtWrapperClass)]/*[contains(@class,:vtWrapperClass)]/ul[contains(@class,:vtMenuClass)]/*[@data-paragraphs-tabs-widget-addmore-group=:fieldName]', [
      ':jsVtWrapperClass' => 'js-form-type-vertical-tabs',
      ':vtWrapperClass' => 'vertical-tabs',
      ':vtMenuClass' => 'vertical-tabs__menu',
      ':vtMenuItemClass' => 'vertical-tabs__menu-item',
      ':fieldName' => $this->sutParagraphNodeFieldName,
    ]));
    $this->assertNotEmpty($vtAddButton, 'The add more button is in the vertical tab menu on the node/add page.');
  }

  /**
   * Test vertical_tabs library moves "Add more" button to tab menu on edit.
   */
  public function testParagraphAddButtonInTabMenuEditPage() {
    $this->drupalLogin($this->sutAdminUser);

    // Check the "Add more" button on a node/edit page (i.e.: pre-populated
    // content) by verifying we can find an add more button is inside the
    // vertical tabs menu list.
    $this->drupalGet($this->sutNode->toUrl('edit-form')->toString());
    $page = $this->getSession()->getPage();
    $vtAddButton = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[contains(@class,:jsVtWrapperClass)]/*[contains(@class,:vtWrapperClass)]/ul[contains(@class,:vtMenuClass)]/*[@data-paragraphs-tabs-widget-addmore-group=:fieldName]', [
      ':jsVtWrapperClass' => 'js-form-type-vertical-tabs',
      ':vtWrapperClass' => 'vertical-tabs',
      ':vtMenuClass' => 'vertical-tabs__menu',
      ':vtMenuItemClass' => 'vertical-tabs__menu-item',
      ':fieldName' => $this->sutParagraphNodeFieldName,
    ]));
    $this->assertNotEmpty($vtAddButton, 'The add more button is in the vertical tab menu on the node/edit page.');
  }

  /**
   * Test core/drupal.vertical-tabs transforms HTML into vertical tabs on add.
   *
   * Note \Drupal\Tests\paragraphs_tabs_widget\Functional\VerticalTabWidgetHtmlOutputTest
   * tests whether we output the HTML we expect. This function tests the HTML
   * that we output is successfully transformed in the user's browser by the
   * core library.
   *
   * @see testParagraphsShowUpInVerticalTabsEditPage()
   */
  public function testParagraphsShowUpInVerticalTabsAddPage() {
    $this->drupalLogin($this->sutAdminUser);

    // Verify vertical tabs JS transforms a node/add page (i.e.: empty values).
    $this->drupalGet(Url::fromRoute('node.add', ['node_type' => $this->sutNodeType->id()])->toString());
    $page = $this->getSession()->getPage();

    // Node/add: Find evidence that JavaScript ran on the page by looking for a
    // class not sent in the initial page response.
    $fieldLabel = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[contains(@class,:jsVtWrapperClass)]/label[text()=:fieldLabelText]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':jsVtWrapperClass' => 'js-form-type-vertical-tabs',
      ':fieldLabelText' => $this->sutParagraphNodeFieldLabel,
    ]));
    $this->assertNotEmpty($fieldLabel, "On the node/add page, the field's label's parent shows vertical-tabs class added by JavaScript.");

    // Node/add: Verify we can find a tab menu for the field.
    $verticalTabsMenu = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//ul[contains(@class,:vtMenuClass)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtMenuClass' => 'vertical-tabs__menu',
    ]));
    $this->assertNotEmpty($verticalTabsMenu, 'Vertical tabs menu is present on the node/add page.');

    // Node/add: Verify there is only one vertical tab menu item for the field,
    // i.e.: for the empty paragraph created by the base
    // entity_reference_paragraphs widget for new entities.
    $verticalTabsMenuItems = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//li[contains(@class,:vtMenuItemClass)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtMenuItemClass' => 'vertical-tabs__menu-item',
    ]));
    $this->assertCount(1, $verticalTabsMenuItems, 'There is one vertical tab menu item on the node/add page.');

    // Node/add: Verify we can find a container for vertical tab panes for the
    // field.
    $verticalTabsPanesWrapper = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[contains(@class,:vtPanesWrapperClass)][@data-paragraphs-tabs-widget-group=:nodeFieldMachName][@data-vertical-tabs-panes]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtPanesWrapperClass' => 'vertical-tabs__panes',
    ]));
    $this->assertNotEmpty($verticalTabsPanesWrapper, 'Vertical tabs panes wrapper is present on the node/add page.');

    // Node/add: Verify there is only one vertical tab pane for the field, i.e.:
    // for the empty paragraph created by the base entity_reference_paragraphs
    // widget for new entities. Verify this tab pane is visible.
    $verticalTabsPanes = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[contains(@class,:vtPanesWrapperClass)]/*[contains(@class,:vtPaneClass)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtPanesWrapperClass' => 'vertical-tabs__panes',
      ':vtPaneClass' => 'vertical-tabs__pane',
    ]));
    $this->assertCount(1, $verticalTabsPanes, 'There are two vertical tab panes on the node/edit page.');
    $this->assertTrue($verticalTabsPanes[0]->isVisible(), 'The first vertical tab pane is visible on the node/add page.');
  }

  /**
   * Test core/drupal.vertical-tabs transforms HTML into vertical tabs on edit.
   *
   * Note \Drupal\Tests\paragraphs_tabs_widget\Functional\VerticalTabWidgetHtmlOutputTest
   * tests whether we output the HTML we expect. This function tests the HTML
   * that we output is successfully transformed in the user's browser by the
   * core library.
   *
   * @see testParagraphsShowUpInVerticalTabsAddPage()
   */
  public function testParagraphsShowUpInVerticalTabsEditPage() {
    $this->drupalLogin($this->sutAdminUser);

    // Verify vertical tabs JS transforms a node/edit page (i.e.: non-empty
    // values).
    $this->drupalGet($this->sutNode->toUrl('edit-form')->toString());
    $page = $this->getSession()->getPage();

    // Node/edit: Find evidence that JavaScript ran on the page by looking for a
    // class not sent in the initial page response.
    $fieldLabel = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[contains(@class,:jsVtWrapperClass)]/label[text()=:fieldLabelText]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':jsVtWrapperClass' => 'js-form-type-vertical-tabs',
      ':fieldLabelText' => $this->sutParagraphNodeFieldLabel,
    ]));
    $this->assertNotEmpty($fieldLabel, "On the node/edit page, the field's label's parent shows vertical-tabs class added by JavaScript.");

    // Node/edit: Verify we can find a tab menu for the field.
    $verticalTabsMenu = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//ul[contains(@class,:vtMenuClass)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtMenuClass' => 'vertical-tabs__menu',
    ]));
    $this->assertNotEmpty($verticalTabsMenu, 'Vertical tabs menu is present on the node/edit page.');

    // Node/edit: Verify there are two vertical tab menu items for the field,
    // i.e.: one for each of the paragraphs we created in the setUp().
    $verticalTabsMenuItems = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//li[contains(@class,:vtMenuItemClass)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtMenuItemClass' => 'vertical-tabs__menu-item',
    ]));
    $this->assertCount(2, $verticalTabsMenuItems, 'There are two vertical tab menu items on the node/edit page.');

    // Node/edit: Verify we can find a container for vertical tab panes for the
    // field.
    $verticalTabsPanesWrapper = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[contains(@class,:vtPanesWrapperClass)][@data-paragraphs-tabs-widget-group=:nodeFieldMachName][@data-vertical-tabs-panes]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtPanesWrapperClass' => 'vertical-tabs__panes',
    ]));
    $this->assertNotEmpty($verticalTabsPanesWrapper, 'Vertical tabs panes wrapper is present on the node/edit page.');

    // Node/edit: Verify there are two vertical tab panes for the field, i.e.:
    // one for each of the paragraphs we created in the setUp(). Verify the
    // first one is visible and the second one is not visible, i.e.: acting like
    // tabs.
    $verticalTabsPanes = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[contains(@class,:vtPanesWrapperClass)]/*[contains(@class,:vtPaneClass)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtPanesWrapperClass' => 'vertical-tabs__panes',
      ':vtPaneClass' => 'vertical-tabs__pane',
    ]));
    $this->assertCount(2, $verticalTabsPanes, 'There are two vertical tab panes on the node/edit page.');
    $this->assertTrue($verticalTabsPanes[0]->isVisible(), 'The first vertical tab pane is visible on the node/edit page.');
    $this->assertFalse($verticalTabsPanes[1]->isVisible(), 'The second vertical tab pane is not visible on the node/edit page.');
  }

  /**
   * Test that vertical_tabs lib populates the tab summaries on node/add page.
   *
   * @see testTabSummaryIsPopulatedEditPage()
   */
  public function testTabSummaryIsPopulatedAddPage() {
    // Set tab summary setting.
    $this->setParagraphsWidgetSettings($this->sutNodeType->id(), $this->sutParagraphNodeFieldName, [
      'summary_selector' => '[name*="' . $this->sutParagraphParagraphFieldName . '"]',
    ], NULL, 'node');

    $this->drupalLogin($this->sutAdminUser);

    // Build any xpath queries we will use multiple times.
    $xpathQueryMenuItemSummary = $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//li[contains(@class,:vtMenuItemClass)]//*[contains(@class,:vtMenuItemSummaryClass)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtMenuItemClass' => 'vertical-tabs__menu-item',
      ':vtMenuItemSummaryClass' => 'vertical-tabs__menu-item-summary',
    ]);

    // Verify summaries on a node/add page (i.e.: empty values).
    $this->drupalGet(Url::fromRoute('node.add', ['node_type' => $this->sutNodeType->id()])->toString());
    $page = $this->getSession()->getPage();

    // Node/add: Find the tab summaries. On a node/add page, there should only
    // be one, with an empty summary-controlling field, and therefore, an empty
    // tab summary.
    $vtMenuItemSummariesBefore = $page->findAll('xpath', $xpathQueryMenuItemSummary);
    $this->assertCount(1, $vtMenuItemSummariesBefore, 'There is initially one menu item summary on the node/add page.');
    $this->assertEmpty($vtMenuItemSummariesBefore[0]->getText(), 'The first menu item summary on the node/add page is initially empty.');

    // Node/add: Generate a random string. Find the summary-controlling field,
    // and set its value to the random string. Wait 10 seconds or until
    // JavaScript updates the tab summary. Then re-run the query for tab
    // summaries (to ensure the data isn't stale), ensure there is still only
    // one tab summary, and verify the first tab summary is now the random
    // string.
    $randomFieldValue = $this->randomString();
    $field = $page->find('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//input[contains(@name,:paragraphFieldMachName)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':paragraphFieldMachName' => $this->sutParagraphParagraphFieldName,
    ]));
    $field->setValue($randomFieldValue);
    $page->waitFor(10, function () use ($vtMenuItemSummariesBefore, $randomFieldValue) {
      return $vtMenuItemSummariesBefore[0] === $randomFieldValue;
    });
    $vtMenuItemSummariesAfter = $page->findAll('xpath', $xpathQueryMenuItemSummary);
    $this->assertCount(1, $vtMenuItemSummariesAfter, 'There is still one menu item summary on the node/add page.');
    $this->assertEquals($randomFieldValue, $vtMenuItemSummariesAfter[0]->getText(), 'The first menu item summary on the node/add page now matches the random value we set.');
  }

  /**
   * Test that vertical_tabs lib populates the tab summaries on node/edit page.
   *
   * @see testTabSummaryIsPopulatedAddPage()
   */
  public function testTabSummaryIsPopulatedEditPage() {
    // Set tab summary setting.
    $this->setParagraphsWidgetSettings($this->sutNodeType->id(), $this->sutParagraphNodeFieldName, [
      'summary_selector' => '[name*="' . $this->sutParagraphParagraphFieldName . '"]',
    ], NULL, 'node');

    $this->drupalLogin($this->sutAdminUser);

    // Build any xpath queries we will use multiple times.
    $xpathQueryMenuItemSummary = $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//li[contains(@class,:vtMenuItemClass)]//*[contains(@class,:vtMenuItemSummaryClass)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtMenuItemClass' => 'vertical-tabs__menu-item',
      ':vtMenuItemSummaryClass' => 'vertical-tabs__menu-item-summary',
    ]);

    // Verify summaries on a node/edit page (i.e.: non-empty values).
    $this->drupalGet($this->sutNode->toUrl('edit-form')->toString());
    $page = $this->getSession()->getPage();

    // Node/edit: Find the tab summaries. On a node/edit page, there should be
    // two, one for each paragraph we generated in setUp(). The summary-
    // controlling fields should be equal to the random strings we saved in
    // setUp(), and therefore, the tab summaries should be equal to those same
    // two random strings.
    $vtMenuItemSummariesBefore = $page->findAll('xpath', $xpathQueryMenuItemSummary);
    $this->assertCount(2, $vtMenuItemSummariesBefore, 'There is initially two menu item summaries on the node/edit page.');
    $this->assertEquals($vtMenuItemSummariesBefore[0]->getText(), $this->sutParagraph1RandomValue, 'The first menu item summary on the node/edit page is the value of the first paragraph field.');
    $this->assertEquals($vtMenuItemSummariesBefore[1]->getText(), $this->sutParagraph2RandomValue, 'The second menu item summary on the node/edit page is the value of the second paragraph field.');

    // Node/edit: Generate a random string. Click the second tab summary to
    // make the contents of that tab visible. Find the summary-controlling field
    // for the second paragraph, and set its value to the random string. Wait 10
    // seconds or until JavaScript updates the tab summary. Then re-run the
    // query for tab summaries (to ensure the data isn't stale), ensure there
    // are still two tab summaries. Verify the first tab summary is still the
    // string entered in setUp(), but the second tab summary has updated to the
    // random string we just generated.
    $newParagraph2RandomValue = $this->randomString();
    $vtMenuItemSummariesBefore[1]->click();
    $fields = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//input[contains(@name,:paragraphFieldMachName)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':paragraphFieldMachName' => $this->sutParagraphParagraphFieldName,
    ]));
    $fields[1]->setValue($newParagraph2RandomValue);
    $page->waitFor(10, function () use ($vtMenuItemSummariesBefore, $newParagraph2RandomValue) {
      return $vtMenuItemSummariesBefore[1] === $newParagraph2RandomValue;
    });
    $vtMenuItemSummariesAfter = $page->findAll('xpath', $xpathQueryMenuItemSummary);
    $this->assertCount(2, $vtMenuItemSummariesAfter, 'There are still two menu item summaries on the node/edit page.');
    $this->assertEquals($vtMenuItemSummariesBefore[0]->getText(), $this->sutParagraph1RandomValue, 'The first menu item summary on the node/edit page is still the value of the first paragraph field.');
    $this->assertEquals($vtMenuItemSummariesBefore[1]->getText(), $newParagraph2RandomValue, 'The second menu item summary on the node/edit page now matches the random value we set.');
  }

  /**
   * Test that the add/remove buttons update the widget properly on node/add.
   */
  public function testAddRemoveButtonsAddPage() {
    $this->drupalLogin($this->sutAdminUser);

    // Prepare any xpath queries we will use multiple times.
    $xpathQueryVtPanes = $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[contains(@class,:vtPanesWrapperClass)]/*[contains(@class,:vtPaneClass)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtPanesWrapperClass' => 'vertical-tabs__panes',
      ':vtPaneClass' => 'vertical-tabs__pane',
    ]);
    $xpathQueryConfirmRemoveButton = $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[@data-paragraph-tabs-widget-tab-group=:nodeFieldMachName]//input[contains(@data-drupal-selector,:confirmButtonSelector)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':confirmButtonSelector' => '-top-links-confirm-remove-button',
    ]);

    // Go to a node/add page.
    $this->drupalGet(Url::fromRoute('node.add', ['node_type' => $this->sutNodeType->id()])->toString());
    $page = $this->getSession()->getPage();

    // Verify there is initially 1 vertical tab pane.
    $verticalTabsPanesPageLoad = $page->findAll('xpath', $xpathQueryVtPanes);
    $this->assertCount(1, $verticalTabsPanesPageLoad, 'There is initially 1 vertical tab pane on the node/add page.');

    // Find and click the add button. Wait for AJAX to finish.
    $addButton = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[@data-paragraphs-tabs-widget-addmore-group]//input[contains(@class,:addMoreSubmit)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':addMoreSubmit' => 'field-add-more-submit',
    ]));
    $this->assertCount(1, $addButton, 'There is exactly 1 Add More button.');
    $addButton[0]->click();
    $page->waitFor(10, function () use ($page, $xpathQueryVtPanes) {
      $results = $page->findAll('xpath', $xpathQueryVtPanes);
      return count($results) === 2;
    });

    // Verify there are now 2 vertical tab panes.
    $verticalTabsPanesAfterAddingOne = $page->findAll('xpath', $xpathQueryVtPanes);
    $this->assertCount(2, $verticalTabsPanesAfterAddingOne, 'After clicking the add button, there are 2 vertical tabs panes on the node/add page.');

    // Find and click the remove button for the first pane. Wait for AJAX to
    // finish. Click the confirm button. Wait for AJAX to finish.
    $removeButtons = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[@data-paragraph-tabs-widget-tab-group=:nodeFieldMachName]//input[contains(@data-drupal-selector,:removeButtonSelector)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':removeButtonSelector' => '-top-links-remove-button',
    ]));
    $this->assertCount(2, $removeButtons, 'There are exactly 2 remove buttons, one per pane.');
    $removeButtons[0]->click();
    $page->waitFor(10, function () use ($page, $xpathQueryConfirmRemoveButton) {
      $results = $page->findAll('xpath', $xpathQueryConfirmRemoveButton);
      return count($results) === 1;
    });
    $confirmRemoveButton = $page->findAll('xpath', $xpathQueryConfirmRemoveButton);
    $this->assertCount(1, $confirmRemoveButton, 'There is exactly 1 confirm remove button.');
    $confirmRemoveButton[0]->click();
    $page->waitFor(10, function () use ($page, $xpathQueryVtPanes) {
      $results = $page->findAll('xpath', $xpathQueryVtPanes);
      return count($results) === 1;
    });

    // Verify there is now 1 vertical tab pane.
    $verticalTabsPanesAfterRemovingOne = $page->findAll('xpath', $xpathQueryVtPanes);
    $this->assertCount(1, $verticalTabsPanesAfterRemovingOne, 'After clicking the first remove button, there is 1 vertical tab pane on the node/add page once more.');
  }

  /**
   * Test that the add/remove buttons update the widget properly on node/edit.
   */
  public function testAddRemoveButtonsEditPage() {
    $this->drupalLogin($this->sutAdminUser);

    // Prepare any xpath queries we will use multiple times.
    $xpathQueryVtPanes = $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[contains(@class,:vtPanesWrapperClass)]/*[contains(@class,:vtPaneClass)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':vtPanesWrapperClass' => 'vertical-tabs__panes',
      ':vtPaneClass' => 'vertical-tabs__pane',
    ]);
    $xpathQueryConfirmRemoveButton = $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[@data-paragraph-tabs-widget-tab-group=:nodeFieldMachName]//input[contains(@data-drupal-selector,:confirmButtonSelector)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':confirmButtonSelector' => '-top-links-confirm-remove-button',
    ]);

    // Go to a node/edit page.
    $this->drupalGet($this->sutNode->toUrl('edit-form')->toString());
    $page = $this->getSession()->getPage();

    // Verify there is initially 1 vertical tab pane.
    $verticalTabsPanesPageLoad = $page->findAll('xpath', $xpathQueryVtPanes);
    $this->assertCount(2, $verticalTabsPanesPageLoad, 'There are initially 2 vertical tab panes on the node/edit page.');

    // Find and click the add button. Wait for AJAX to finish.
    $addButton = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[@data-paragraphs-tabs-widget-addmore-group]//input[contains(@class,:addMoreSubmit)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':addMoreSubmit' => 'field-add-more-submit',
    ]));
    $this->assertCount(1, $addButton, 'There is exactly 1 Add More button.');
    $addButton[0]->click();
    $page->waitFor(10, function () use ($page, $xpathQueryVtPanes) {
      $results = $page->findAll('xpath', $xpathQueryVtPanes);
      return count($results) === 3;
    });

    // Verify there are now 3 vertical tab panes.
    $verticalTabsPanesAfterAddingOne = $page->findAll('xpath', $xpathQueryVtPanes);
    $this->assertCount(3, $verticalTabsPanesAfterAddingOne, 'After clicking the add button, there are 3 vertical tabs panes on the node/edit page.');

    // Find and click the remove button for the first pane. Wait for AJAX to
    // finish. Click the confirm button. Wait for AJAX to finish.
    $removeButtons = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[@data-paragraph-tabs-widget-tab-group=:nodeFieldMachName]//input[contains(@data-drupal-selector,:removeButtonSelector)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':removeButtonSelector' => '-top-links-remove-button',
    ]));
    $this->assertCount(3, $removeButtons, 'There are exactly 3 remove buttons, one per pane.');
    $removeButtons[0]->click();
    $page->waitFor(10, function () use ($page, $xpathQueryConfirmRemoveButton) {
      $results = $page->findAll('xpath', $xpathQueryConfirmRemoveButton);
      return count($results) === 1;
    });
    $confirmRemoveButton = $page->findAll('xpath', $xpathQueryConfirmRemoveButton);
    $this->assertCount(1, $confirmRemoveButton, 'There is exactly 1 confirm remove button.');
    $confirmRemoveButton[0]->click();
    $page->waitFor(10, function () use ($page, $xpathQueryVtPanes) {
      $results = $page->findAll('xpath', $xpathQueryVtPanes);
      return count($results) === 2;
    });

    // Verify there is now 1 vertical tab pane.
    $verticalTabsPanesAfterRemovingOne = $page->findAll('xpath', $xpathQueryVtPanes);
    $this->assertCount(2, $verticalTabsPanesAfterRemovingOne, 'After clicking the first remove button, there are 2 vertical tab panes on the node/add page once more.');
  }

}
