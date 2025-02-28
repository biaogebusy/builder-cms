<?php

namespace Drupal\Tests\diff\Functional;

/**
 * Tests the Diff admin forms.
 *
 * @group diff
 */
class DiffAdminFormsTest extends DiffTestBase {

  use CoreVersionUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'help',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests the descriptions in the Settings UI.
   */
  public function testSettingsUi(): void {
    // Enable the help block.
    $this->drupalPlaceBlock('help_block', ['region' => 'help']);

    $this->drupalGet('admin/config/content/diff/general');
    // Check the settings introduction text.
    $this->assertSession()->pageTextContains('Configurations for the revision comparison functionality and diff layout plugins.');
    // Check the layout plugins descriptions.
    $this->assertSession()->pageTextContains('Field based layout, displays revision comparison side by side.');
    $this->assertSession()->pageTextContains('Field based layout, displays revision comparison line by line.');
  }

  /**
   * Tests the Settings tab.
   */
  public function testSettingsTab(): void {
    $this->drupalGet('admin/config/content/diff/general');
    $this->assertSession()->optionExists('View mode', 'full');
    $this->assertSession()->optionExists('View mode', 'default');
    $this->assertSession()->optionExists('View mode', 'diff');
    $this->submitForm([
      'radio_behavior' => 'linear',
      'context_lines_leading' => 10,
      'context_lines_trailing' => 5,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

  /**
   * Tests the module requirements.
   */
  public function testRequirements(): void {
    \Drupal::moduleHandler()->loadInclude('diff', 'install');
    $requirements = diff_requirements('runtime');
    $this->assertEquals($requirements['html_diff_advanced']['title'], 'Diff');

    $has_htmlDiffAdvanced = class_exists('\HtmlDiffAdvanced');
    if (!$has_htmlDiffAdvanced) {
      // The plugin is disabled dependencies are missing.
      $this->assertEquals($requirements['html_diff_advanced']['value'], 'Visual inline layout');
    }
    else {
      // The plugin is enabled by default if dependencies are met.
      $this->assertEquals($requirements['html_diff_advanced']['value'], 'Installed correctly');
    }
  }

  /**
   * Tests the Configurable Fields tab.
   */
  public function testConfigurableFieldsTab(): void {
    $this->drupalGet('admin/config/content/diff/fields');

    // Test changing type without changing settings.
    $edit = [
      'fields[node__body][plugin][type]' => 'text_summary_field_diff_builder',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->fieldValueEquals('fields[node__body][plugin][type]', 'text_summary_field_diff_builder');
    $edit = [
      'fields[node__body][plugin][type]' => 'text_field_diff_builder',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->fieldValueEquals('fields[node__body][plugin][type]', 'text_field_diff_builder');

    $this->submitForm([], 'node__body_settings_edit');
    $this->assertSession()->pageTextContains('Plugin settings: Text');
    $edit = [
      'fields[node__body][settings_edit_form][settings][show_header]' => TRUE,
      'fields[node__body][settings_edit_form][settings][compare_format]' => FALSE,
      'fields[node__body][settings_edit_form][settings][markdown]' => 'filter_xss_all',
    ];
    $this->submitForm($edit, 'node__body_plugin_settings_update');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Your settings have been saved.');

    // Check the values were saved.
    $this->submitForm([], 'node__body_settings_edit');
    $this->assertSession()->fieldValueEquals('fields[node__body][settings_edit_form][settings][markdown]', 'filter_xss_all');

    // Edit another field.
    $this->submitForm([], 'node__title_settings_edit');
    $edit = [
      'fields[node__title][settings_edit_form][settings][markdown]' => 'filter_xss_all',
    ];
    $this->submitForm($edit, 'node__title_plugin_settings_update');
    $this->submitForm([], 'Save');

    // Check both fields and their config values.
    $this->submitForm([], 'node__body_settings_edit');
    $this->assertSession()->fieldValueEquals('fields[node__body][settings_edit_form][settings][markdown]', 'filter_xss_all');
    $this->submitForm([], 'node__title_settings_edit');
    $this->assertSession()->fieldValueEquals('fields[node__title][settings_edit_form][settings][markdown]', 'filter_xss_all');

    // Save field settings without changing anything and assert the config.
    $this->submitForm([], 'Save');
    $this->submitForm([], 'node__body_settings_edit');
    $this->assertSession()->fieldValueEquals('fields[node__body][settings_edit_form][settings][markdown]', 'filter_xss_all');
    $this->submitForm([], 'node__title_settings_edit');
    $this->assertSession()->fieldValueEquals('fields[node__title][settings_edit_form][settings][markdown]', 'filter_xss_all');

    $edit = [
      'fields[node__sticky][plugin][type]' => 'hidden',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->fieldValueEquals('fields[node__sticky][plugin][type]', 'hidden');
  }

  /**
   * Tests the Compare Revisions vertical tab.
   */
  public function testPluginWeight(): void {
    // Create a node with a revision.
    $edit = [
      'title[0][value]' => 'great_title',
      'body[0][value]' => '<p>great_body</p>',
    ];
    $this->drupalPostNodeForm('node/add/article', $edit, 'Save');
    $this->clickLink('Edit');
    $edit = [
      'title[0][value]' => 'greater_title',
      'body[0][value]' => '<p>greater_body</p>',
    ];
    $this->submitForm($edit, 'Save');

    // Assert the diff display uses the classic layout.
    $node = $this->getNodeByTitle('greater_title');
    $this->drupalGet('node/' . $node->id() . '/revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->linkExists('Unified fields');
    $this->assertSession()->linkExists('Split fields');
    $this->assertSession()->linkExists('Raw');
    $this->assertSession()->linkExists('Strip tags');
    $text = $this->xpath('//tbody/tr[4]/td[3]');
    $this->assertEquals(htmlspecialchars_decode(strip_tags($text[0]->getHtml())), '<p>great_body</p>');

    // Change the settings of the layouts, disable the single column.
    $edit = [
      'layout_plugins[split_fields][weight]' => '11',
      'layout_plugins[unified_fields][enabled]' => FALSE,
    ];
    $this->drupalGet('admin/config/content/diff/general');
    $this->submitForm($edit, 'Save configuration');

    // Assert the diff display uses the markdown layout.
    $this->drupalGet('node/' . $node->id() . '/revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkNotExists('Unified fields');
    $this->assertSession()->linkExists('Split fields');
    $this->clickLink('Split fields');
    $this->assertSession()->linkExists('Raw');
    $this->assertSession()->linkExists('Strip tags');
    $this->clickLink('Strip tags');
    $assert_session = $this->assertSession();
    $assert_session->elementContains('css', 'tr:nth-child(4) td:nth-child(2)', 'great_body');

    // Change the settings of the layouts, enable single column.
    $edit = [
      'layout_plugins[unified_fields][enabled]' => TRUE,
      'layout_plugins[split_fields][enabled]' => FALSE,
    ];
    $this->drupalGet('admin/config/content/diff/general');
    $this->submitForm($edit, 'Save configuration');

    // Test the validation of form.
    $edit = [
      'layout_plugins[unified_fields][enabled]' => FALSE,
      'layout_plugins[split_fields][enabled]' => FALSE,
    ];
    $this->drupalGet('admin/config/content/diff/general');
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('At least one layout plugin needs to be enabled.');

    // Assert the diff display uses the single column layout.
    $this->drupalGet('node/' . $node->id() . '/revisions');
    $this->submitForm([], 'Compare selected revisions');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Unified fields');
    $this->assertSession()->linkNotExists('Split fields');
    $this->assertSession()->linkExists('Raw');
    $this->assertSession()->linkExists('Strip tags');
    $assert_session->elementTextContains('css', 'tr:nth-child(5) td:nth-child(4)', '<p>great_body</p>');
    $this->clickLink('Strip tags');
    $assert_session->elementContains('css', 'tr:nth-child(5) td:nth-child(2)', 'great_body');
    $assert_session->elementTextNotContains('css', 'tr:nth-child(5) td:nth-child(2)', '<p>');
  }

}
