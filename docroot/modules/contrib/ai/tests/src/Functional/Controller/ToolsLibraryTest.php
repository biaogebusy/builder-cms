<?php

namespace Drupal\Tests\ai\Functional\Controller;

use Drupal\ai\AiToolsLibraryState;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the tools library controller.
 *
 * @group ai
 */
class ToolsLibraryTest extends BrowserTestBase {

  /**
   * The modules to enable for this test.
   *
   * @var array
   */

  protected static $modules = [
    'system',
    'user',
    'ai',
    'ai_test',
  ];

  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the access to overview page is forbidden without opener state.
   */
  public function testOverviewPageWithoutOpenerState(): void {
    $account = $this->drupalCreateUser([
      'access ai tools overview',
      'access administration pages',
      'access content',
      'view the administration theme',
    ]);

    $this->drupalLogin($account);

    $this->drupalGet('admin/config/ai/tools');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the overview page works with opener query parameters.
   */
  public function testOverviewPageWithOpenerState(): void {
    $account = $this->drupalCreateUser([
      'access ai tools overview',
      'access administration pages',
      'access content',
      'view the administration theme',
    ]);

    $this->drupalLogin($account);

    $parameters = [
      'ai_tools_library_opener_id' => 'ai_tools_library.opener.form_element',
      'ai_tools_library_allowed_groups' => [
        '_all',
        'information_tools',
        'modification_tools',
        'other_fallback',
        'drupal_actions',
      ],
      'ai_tools_library_selected_group' => '_all',
      'ai_tools_library_opener_parameters' => [
        'field_widget_id' => 'prompt_detail-tools_box-tools-ids',
      ],
    ];

    $state = new AiToolsLibraryState($parameters);
    $parameters['hash'] = $state->getHash();

    $this->drupalGet('admin/config/ai/tools', ['query' => $parameters]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Calculator');
    $this->assertSession()->pageTextContains('Weather');
    $this->assertSession()->pageTextContains('Trigger');
  }

}
