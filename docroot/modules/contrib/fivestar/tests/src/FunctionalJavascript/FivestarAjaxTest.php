<?php

namespace Drupal\Tests\fivestar\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Functional Javascript tests for the Fivestar module.
 *
 * @group Fivestar
 */
class FivestarAjaxTest extends FivestarAjaxTestBase {

  /**
   * Test that users can rate content when viewing with ajax.
   */
  public function testViewerRatingAjax(): void {
    // Add a field rated while viewing.
    $this->createFivestarFieldTwo();

    // Create a node, view it and give it a five-star rating.
    $node = $this->createNode(['type' => 'test_node_type']);
    $this->drupalLogin($this->voterUser);
    $this->drupalGet($node->toUrl());
    $this->getSession()->getPage()->clickLink('Give it 5/5');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertFivestarRatingOutput('5');

    // Reload the page to ensure the vote is still displayed.
    $this->drupalGet($node->toUrl());
    $this->assertFivestarRatingOutput('5');
  }

  /**
   * Test that users cannot re-vote.
   */
  public function testUserNoRevote(): void {
    // Add a field rated while viewing.
    $this->createFivestarFieldTwo([
      'field_settings' => [
        'allow_revote' => FALSE,
      ],
    ]);

    // Create a node, view it and give it a five-star rating.
    $node = $this->createNode(['type' => 'test_node_type']);
    $this->drupalLogin($this->voterUser);
    $this->drupalGet($node->toUrl());
    $this->getSession()->getPage()->clickLink('Give it 5/5');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertFivestarRatingOutput('5');
    $this->assertFalse($this->getSession()->getPage()->hasLink('Give it 5/5'));

    // Reload the page to ensure the vote is still displayed.
    $this->drupalGet($node->toUrl());
    $this->assertFivestarRatingOutput('5');
  }

  /**
   * Creates a fivestar field and storage, and adds it to a content type.
   *
   * This is a modified version of createFivestarField() inherited from
   * FivestarAjaxTestBase.
   *
   * @param array $options
   *   (optional) An associative array of options for the field and instance.
   *   The keys can be one or more of:
   *   - field_settings: Defaults to an empty array.
   *   - view_display: Defaults to an empty array.
   *   - form_display: Defaults to an empty array.
   */
  protected function createFivestarFieldTwo(array $options = []): void {
    $options += [
      'field_settings' => [],
      'view_display' => [],
      'form_display' => [],
    ];

    // Define and create the storage.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'fivestar_test',
      'entity_type' => 'node',
      'type' => 'fivestar',
      'cardinality' => 1,
      'settings' => [
        'vote_type' => 'vote',
      ],
    ]);
    $field_storage->save();

    // Define and create the field.
    $field = FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => $field_storage->getName(),
      'label' => 'Fivestar test field',
      'bundle' => 'test_node_type',
      'settings' => $options['field_settings'],
    ]);
    $field->save();

    // Add the field to the view and form display's.
    $this->displayRepository->getViewDisplay('node', 'test_node_type', 'default')
      ->setComponent('fivestar_test', $options['view_display'])
      ->save();
    $this->displayRepository->getFormDisplay('node', 'test_node_type', 'default')
      ->setComponent('fivestar_test', $options['form_display'])
      ->save();
  }

}
