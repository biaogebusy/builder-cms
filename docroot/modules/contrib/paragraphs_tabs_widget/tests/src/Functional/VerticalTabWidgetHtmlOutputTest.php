<?php

namespace Drupal\Tests\paragraphs_tabs_widget\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;
use Drupal\Tests\paragraphs_tabs_widget\Traits\VerticalTabsTestTrait;

/**
 * Test vertical tab widget outputs HTML that will work with vertical tabs JS.
 *
 * @group paragraphs_tabs_widget
 */
class VerticalTabWidgetHtmlOutputTest extends BrowserTestBase {
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
   * A user with administrative privileges in the system under test.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $sutAdminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpRandomParagraphTypeWithRandomTextField();
    $this->setUpRandomNodeTypeWithRandomParagraphRefField();

    // Create a user that can create nodes.
    $this->sutAdminUser = $this->createUser(['administer nodes', 'bypass node access']);
  }

  /**
   * Test the widget on a create page, i.e.: no existing data.
   */
  public function testParagraphTabWidgetCreate() {
    // Log in as the user, and load the node/add page for the content type.
    $this->drupalLogin($this->sutAdminUser);
    $this->drupalGet(Url::fromRoute('node.add', ['node_type' => $this->sutNodeType->id()])->toString());
    $this->assertSession()->statusCodeEquals(200);
    $page = $this->getSession()->getPage();

    // Assert the widget appears and has the correct HTML structure.
    $page->hasField($this->sutParagraphNodeFieldName);

    // Validate there is a label for the field/wrapper.
    $label = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//label[text()=:fieldLabelText]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':fieldLabelText' => $this->sutParagraphNodeFieldLabel,
    ]));
    $this->assertNotEmpty($label, 'Field label is present.');
    $this->assertCount(1, $label, 'There is exactly one field label on the create node form.');

    // Validate the field/wrapper has the correct HTML attributes.
    $vtWrapper = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[@data-paragraphs-tabs-widget-group=:nodeFieldMachName][@data-vertical-tabs-panes]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
    ]));
    $this->assertNotEmpty($vtWrapper, 'Vertical tab wrapper is present.');
    $this->assertCount(1, $vtWrapper, 'There is exactly one vertical tab wrapper on the create node form.');

    // Validate the label is adjacent to the field/wrapper.
    $labelAndVtWrapper = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//label[text()=:fieldLabelText]/following-sibling::*[1][@data-paragraphs-tabs-widget-group=:nodeFieldMachName][@data-vertical-tabs-panes]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      ':fieldLabelText' => $this->sutParagraphNodeFieldLabel,
    ]));
    $this->assertNotEmpty($labelAndVtWrapper, 'Label and vertical tab wrapper are sibling elements.');

    // Validate the field/wrapper has a "tab" detail element as a child element
    // (important because the JavaScript won't work if tabs are grandchildren or
    // later descendants).
    $detailsTabIsChildOfVtWrapper = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[@data-paragraphs-tabs-widget-group=:nodeFieldMachName][@data-vertical-tabs-panes]/details[@data-paragraph-tabs-widget-tab-group=:nodeFieldMachName]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
    ]));
    $this->assertNotEmpty($detailsTabIsChildOfVtWrapper, 'Details tab element is a child (i.e.: not a later descendant) of the vertical tab wrapper.');
    $this->assertCount(1, $detailsTabIsChildOfVtWrapper, 'There is exactly one tab element in the vertical tab wrapper on the create node form.');

    // Validate that the paragraph's field is a descendant of a tab. This proves
    // the paragraph's form is inside the tab.
    $paragraphFieldIsDescendantOfTab = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//details[@data-paragraph-tabs-widget-tab-group=:nodeFieldMachName]//input[contains(@name,:paraFieldMachName)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      // The name of the field in the paragraph will be in square brackets
      // somewhere in the input element's name attribute according to Drupal's
      // naming conventions for deeply-nested fields.
      ':paraFieldMachName' => '[' . $this->sutParagraphParagraphFieldName . ']',
    ]));
    $this->assertNotEmpty($paragraphFieldIsDescendantOfTab, "Paragraph's text field is a descendant of the tab.");
    $this->assertCount(1, $paragraphFieldIsDescendantOfTab, "There is exactly one instance of the paragraph's text field on the create node form, because there is only one tab.");

    // Validate the field/wrapper has an "add more" button as a child element
    // (important because the JavaScript won't work if the add more element is a
    // grandchild or later descendant).
    $addMoreIsChildOfVtWrapper = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group-wrapper=:nodeFieldMachName]//*[@data-paragraphs-tabs-widget-group=:nodeFieldMachName][@data-vertical-tabs-panes]/div[@data-paragraphs-tabs-widget-addmore-group=:nodeFieldMachName]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
    ]));
    $this->assertNotEmpty($addMoreIsChildOfVtWrapper, 'Add more element is a child (i.e.: not a later descendant) of the vertical tab wrapper.');
    $this->assertCount(1, $addMoreIsChildOfVtWrapper, 'There is exactly one add more button because there is only one field.');
  }

  /**
   * Test the widget on an edit page, i.e.: with existing data.
   */
  public function testParagraphTabWidgetUpdate() {
    $sutParagraphParagraphFieldValue1 = $this->getRandomGenerator()->string();
    $sutParagraphParagraphFieldValue2 = $this->getRandomGenerator()->string();
    $sutParagraphParagraphFieldValue3 = $this->getRandomGenerator()->string();

    // Generate a node we will later edit.
    $sutNode = $this->drupalCreateNode([
      'type' => $this->sutNodeType->id(),
      'title' => $this->getRandomGenerator()->string(22),
      $this->sutParagraphNodeFieldName => [
        $this->drupalCreateParagraph([
          'type' => $this->sutParagraphTypeName,
          $this->sutParagraphParagraphFieldName => $sutParagraphParagraphFieldValue1,
        ]),
        $this->drupalCreateParagraph([
          'type' => $this->sutParagraphTypeName,
          $this->sutParagraphParagraphFieldName => $sutParagraphParagraphFieldValue2,
        ]),
        $this->drupalCreateParagraph([
          'type' => $this->sutParagraphTypeName,
          $this->sutParagraphParagraphFieldName => $sutParagraphParagraphFieldValue3,
        ]),
      ],
    ]);

    // Log in as the user, and load the node/edit page.
    $this->drupalLogin($this->sutAdminUser);
    $this->drupalGet($sutNode->toUrl('edit-form')->toString());
    $this->assertSession()->statusCodeEquals(200);
    $page = $this->getSession()->getPage();

    // Validate there is a label for the field/wrapper.
    $label = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//label[text()=:fieldLabelText]', [
      ':fieldLabelText' => $this->sutParagraphNodeFieldLabel,
    ]));
    $this->assertNotEmpty($label, 'Field label is present.');
    $this->assertCount(1, $label, 'There is exactly one field label on the edit node form.');

    // Validate the field/wrapper has the correct HTML attributes.
    $vtWrapper = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group=:nodeFieldMachName][@data-vertical-tabs-panes]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
    ]));
    $this->assertNotEmpty($vtWrapper, 'Vertical tab wrapper is present.');
    $this->assertCount(1, $vtWrapper, 'There is exactly one vertical tab wrapper on the edit node form.');

    // Validate the label is adjacent to the field/wrapper.
    $labelAndVtWrapper = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//label[text()=:fieldLabelText]/following-sibling::*[1][@data-paragraphs-tabs-widget-group=:nodeFieldMachName][@data-vertical-tabs-panes]', [
      ':fieldLabelText' => $this->sutParagraphNodeFieldLabel,
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
    ]));
    $this->assertNotEmpty($labelAndVtWrapper, 'Label and vertical tab wrapper are sibling elements.');

    // Validate the field/wrapper has a "tab" detail element as a child element
    // (important because the JavaScript won't work if tabs are grandchildren or
    // later descendants).
    $detailsTabIsChildOfVtWrapper = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group=:nodeFieldMachName][@data-vertical-tabs-panes]/details[@data-paragraph-tabs-widget-tab-group=:nodeFieldMachName]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
    ]));
    $this->assertNotEmpty($detailsTabIsChildOfVtWrapper, 'Details tab element is a child (i.e.: not a later descendant) of the vertical tab wrapper.');
    $this->assertCount(3, $detailsTabIsChildOfVtWrapper, 'There are three tab elements in the vertical tab wrapper on the edit node form (three existing paragraphs).');

    // Validate that the paragraph's field is a descendant of a tab. This proves
    // the paragraph's form is inside the tab.
    $paragraphFieldIsDescendantOfTab = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//details[@data-paragraph-tabs-widget-tab-group=:nodeFieldMachName]//input[contains(@name,:paraFieldMachName)]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
      // The name of the field in the paragraph will be in square brackets
      // somewhere in the input element's name attribute according to Drupal's
      // naming conventions for deeply-nested fields.
      ':paraFieldMachName' => '[' . $this->sutParagraphParagraphFieldName . ']',
    ]));
    $this->assertNotEmpty($paragraphFieldIsDescendantOfTab, "Paragraph's text field is a descendant of the tab.");
    $this->assertCount(3, $paragraphFieldIsDescendantOfTab, "There are three instances of the paragraph's text field on the edit node form, because there are three tabs.");

    // Validate the paragraph field values we saved at the start of this test
    // are shown in the correct fields.
    $this->assertEquals($paragraphFieldIsDescendantOfTab[0]->getValue(), $sutParagraphParagraphFieldValue1, 'Paragraph 1 field value matches what we saved.');
    $this->assertEquals($paragraphFieldIsDescendantOfTab[1]->getValue(), $sutParagraphParagraphFieldValue2, 'Paragraph 2 field value matches what we saved.');
    $this->assertEquals($paragraphFieldIsDescendantOfTab[2]->getValue(), $sutParagraphParagraphFieldValue3, 'Paragraph 3 field value matches what we saved.');

    // Validate the field/wrapper has an "add more" button as a child element
    // (important because the JavaScript won't work if the add more element is a
    // grandchild or later descendant).
    $addMoreIsChildOfVtWrapper = $page->findAll('xpath', $this->assertSession()->buildXPathQuery('//*[@data-paragraphs-tabs-widget-group=:nodeFieldMachName][@data-vertical-tabs-panes]/div[@data-paragraphs-tabs-widget-addmore-group=:nodeFieldMachName]', [
      ':nodeFieldMachName' => $this->sutParagraphNodeFieldName,
    ]));
    $this->assertNotEmpty($addMoreIsChildOfVtWrapper, 'Add more element is a child (i.e.: not a later descendant) of the vertical tab wrapper.');
    $this->assertCount(1, $addMoreIsChildOfVtWrapper, 'There is exactly one add more button because there is only one field.');
  }

}
