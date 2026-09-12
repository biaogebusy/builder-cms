<?php

namespace Drupal\Tests\ckeditor5_plugin_pack_find_and_replace\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\ckeditor5\Plugin\Editor\CKEditor5;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\ckeditor5\FunctionalJavascript\CKEditor5TestBase;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\user\RoleInterface;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Verify presence and function of findAndReplace button.
 *
 * @group ckeditor_find
 */
class FindReplaceTest extends CKEditor5TestBase {
  use CKEditor5TestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ckeditor_find',
  ];

  /**
   * Verify button and functionality.
   */
  public function testFindReplacePlugin() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    FilterFormat::create([
      'format' => 'ckeditor5',
      'name' => 'CKEditor 5 with find-replace',
      'roles' => [RoleInterface::AUTHENTICATED_ID],
    ])->save();
    Editor::create([
      'format' => 'ckeditor5',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => ['findAndReplace'],
        ],
      ],
    ])->save();
    $this->assertSame([], array_map(
      function (ConstraintViolation $v) {
        return (string) $v->getMessage();
      },
      iterator_to_array(CKEditor5::validatePair(
        Editor::load('ckeditor5'),
        FilterFormat::load('ckeditor5')
      ))
    ));
    $values = [
      'body' => [
        'value' => '<p>This is the first test content paragraph</p><p>This is the second test content paragraph</p>',
        'format' => 'ckeditor5',
      ],
    ];
    $node = $this->drupalCreateNode($values);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->waitForEditor();
    // Ensure that CKEditor 5 is focused.
    $this->click('.ck-content');
    $this->assertEditorButtonEnabled('Find and replace');
    $this->pressEditorButton('Find and replace');
    $findText = $page->find('css', 'fieldset.ck-find-and-replace-form__find  input');
    $this->isInstanceOf(NodeElement::class, $findText);
    $findButton = $page->find('css', 'fieldset.ck-find-and-replace-form__find  button.ck-button-find');
    $this->isInstanceOf(NodeElement::class, $findButton);
    $results = $page->find('css', 'span.ck-results-counter');
    $this->isInstanceOf(NodeElement::class, $results);
    $findText->setValue('paragraph');
    $findButton->click();
    $resultText = $results->getText();
    $this->assertStringContainsString('of 2', $resultText);
  }

}
