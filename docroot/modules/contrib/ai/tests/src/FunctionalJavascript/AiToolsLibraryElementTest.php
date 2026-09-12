<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests removing tools from the AI Tools Library form element.
 *
 * @group ai
 * @group 3586058
 */
#[RunTestsInSeparateProcesses]
class AiToolsLibraryElementTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'file',
    'user',
    'system',
  ];

  /**
   * AI Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $aiAdmin;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $user = $this->drupalCreateUser([
      'administer ai',
    ]);
    $this->assertNotFalse($user, 'AI admin user should be created successfully.');
    $this->aiAdmin = $user;
  }

  /**
   * Tests that the only selected tool can be removed.
   *
   * Removing the last remaining tool submits an empty selection, which used to
   * be treated as "no input" and restored the default value, so that the tool
   * reappeared and could never be deleted.
   *
   * @see https://www.drupal.org/project/ai_agents/issues/3586058
   */
  public function testRemoveOnlySelectedTool(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/test-form-elements', [
      'query' => ['tools' => 'ai:calculator'],
    ]);

    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Calculator');

    $this->removeTool('ai:calculator');

    $assert_session->pageTextContains('You have not selected any tools yet.');
    $assert_session->pageTextNotContains('Calculator');
    $this->assertSame('', $this->getSelectedToolsValue());

    // The empty selection has to survive the submission as well.
    $this->submitForm([], 'Submit');
    $assert_session->pageTextContains('AI Tools Library - Array');
    $assert_session->pageTextNotContains('ai:calculator');
  }

  /**
   * Tests removing one of several selected tools.
   */
  public function testRemoveOneOfSeveralSelectedTools(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/test-form-elements', [
      'query' => ['tools' => 'ai:calculator,ai:weather'],
    ]);

    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Calculator');
    $assert_session->pageTextContains('Weather');

    $this->removeTool('ai:calculator');

    $assert_session->pageTextNotContains('Calculator');
    $assert_session->pageTextContains('Weather');
    $this->assertSame('ai:weather', $this->getSelectedToolsValue());
  }

  /**
   * Tests that a selection stored as a map of tool id => enabled is rendered.
   *
   * Configuration stores the selection in that shape, and reading it as a list
   * of tool ids used to produce "Undefined array key" warnings.
   */
  public function testDefaultValueStoredAsMap(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/test-form-elements', [
      'query' => [
        'tools' => 'ai:calculator',
        'tools_as_map' => 1,
      ],
    ]);

    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Calculator');
    $assert_session->pageTextNotContains('Undefined array key');
    $this->assertSame('ai:calculator', $this->getSelectedToolsValue());

    $this->removeTool('ai:calculator');
    $assert_session->pageTextContains('You have not selected any tools yet.');
  }

  /**
   * Tests that an unknown tool id stays visible and removable.
   *
   * A tool disappears when the module providing it is uninstalled, and invalid
   * ids could be persisted by earlier versions of this element. Neither may
   * result in warnings or in an entry that cannot be removed.
   */
  public function testUnknownToolIsRemovable(): void {
    $this->drupalLogin($this->aiAdmin);
    $this->drupalGet('admin/config/ai/test-form-elements', [
      'query' => ['tools' => 'ai:no_such_tool'],
    ]);

    $assert_session = $this->assertSession();
    $assert_session->pageTextNotContains('Undefined array key');
    $assert_session->pageTextContains('This tool is not available.');

    $this->removeTool('ai:no_such_tool');
    $assert_session->pageTextContains('You have not selected any tools yet.');
  }

  /**
   * Clicks the remove button of a tool and waits for the Ajax rebuild.
   *
   * @param string $tool_id
   *   The id of the tool to remove.
   */
  protected function removeTool(string $tool_id): void {
    $remove = $this->assertSession()->elementExists('css', '.ai-tools-library-item__remove[data-tool-id="' . $tool_id . '"]');
    $remove->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Returns the value of the hidden field holding the selected tool ids.
   *
   * @return string
   *   The comma-delimited list of selected tool ids.
   */
  protected function getSelectedToolsValue(): string {
    $field = $this->assertSession()->elementExists('css', '[data-ai-tools-library-form-element-value]');

    return (string) $field->getValue();
  }

}
