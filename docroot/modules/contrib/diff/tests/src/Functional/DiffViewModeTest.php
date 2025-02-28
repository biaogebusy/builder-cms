<?php

namespace Drupal\Tests\diff\Functional;

/**
 * Tests field visibility when using a custom view mode.
 *
 * @group diff
 */
class DiffViewModeTest extends DiffTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui'];

  /**
   * Tests field visibility using a custom view mode.
   */
  public function testViewMode(): void {
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Sample node',
      'body' => [
        'value' => 'Foo',
      ],
    ]);

    // Edit the article and change the email.
    $edit = [
      'body[0][value]' => 'Fighters',
      'revision' => TRUE,
    ];
    $this->drupalGet($node->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Set the Body field to hidden in the diff view mode.
    $edit = [
      'fields[body][region]' => 'hidden',
    ];
    $this->drupalGet('admin/structure/types/manage/article/display');
    $this->submitForm($edit, 'Save');
    $this->drupalGet('admin/structure/types/manage/article/display/teaser');
    $this->submitForm($edit, 'Save');

    // Check the difference between the last two revisions.
    $this->drupalGet('node/' . $node->id() . '/revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->pageTextNotContains('Body');
    $this->assertSession()->pageTextNotContains('Foo');
    $this->assertSession()->pageTextNotContains('Fighters');
  }

  /**
   * Tests the default view mode setting.
   */
  public function testDefaultViewModeSetting(): void {
    // Enable visual inline.
    $this->config('diff.settings')
      ->set('general_settings.layout_plugins.visual_inline.enabled', TRUE)
      ->save();

    $this->drupalLogin($this->rootUser);

    $node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Sample node',
      'body' => [
        'value' => 'Foo',
      ],
    ]);
    $node->set('body', 'Fighters');
    $node->setNewRevision(TRUE);
    $node->save();

    $this->drupalGet('node/' . $node->id() . '/revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertEquals('Default', $this->getActiveViewMode());

    // Update the default view mode.
    $this->config('diff.settings')
      ->set('general_settings.visual_default_view_mode', 'teaser')
      ->save();

    $this->getSession()->reload();
    $this->assertEquals('Teaser', $this->getActiveViewMode());

    // Test query param defaulting.
    $this->clickLink('Default');
    $this->assertEquals('Default', $this->getActiveViewMode());
  }

  /**
   * Get the active view mode on the page.
   */
  protected function getActiveViewMode(): string {
    $xpath = $this->assertSession()->buildXPathQuery('//a[contains(@href, "?view_mode=")]');
    return $this->getSession()->getPage()->find('xpath', $xpath)?->getText();
  }

}
