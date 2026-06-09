<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Dto\HostnameFilterDto;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;

/**
 * Tests that ProviderProxy applies HostnameFilterDto to tool results.
 *
 * Regression test for the bug where setting a HostnameFilterDto on a
 * ChatInput only affected ChatMessage::getText() and not the strings
 * carried in tool call arguments (ToolsPropertyResult). Also covers the
 * singleton spillover: a DTO applied for one call must not leak into
 * subsequent calls on the same request.
 *
 * @group ai
 */
class ProviderProxyHostnameFilterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'user',
    'field',
    'system',
  ];

  /**
   * The hostname filter service.
   *
   * @var \Drupal\ai\Service\HostnameFilter
   */
  protected $hostnameFilter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['ai', 'ai_test']);
    $this->installEntitySchema('ai_mock_provider_result');

    // Pin the singleton to a known starting state: empty allowed_hosts so
    // that any external host is filtered by default.
    $this->hostnameFilter = \Drupal::service('ai.hostname_filter_service');
    $this->hostnameFilter->setAllowedDomains([]);
  }

  /**
   * Build the ChatInput that the YAML fixture matches.
   */
  protected function buildTriggerInput(): ChatInput {
    return new ChatInput([
      new ChatMessage('user', 'hostname-filter-tool-bug-test-trigger'),
    ]);
  }

  /**
   * Pull the single tool argument string out of a ChatOutput.
   */
  protected function getFirstToolArgValue(ChatOutput $output): string {
    $message = $output->getNormalized();
    $this->assertInstanceOf(ChatMessage::class, $message);
    $tools = $message->getTools();
    $this->assertNotEmpty($tools, 'Expected the fixture response to carry a tool call.');
    $args = $tools[0]->getArguments();
    $this->assertNotEmpty($args, 'Expected the tool call to carry arguments.');
    return (string) $args[0]->getValue();
  }

  /**
   * Baseline: with no DTO and empty allowed hosts, the URL is filtered out.
   */
  public function testDefaultFilteringRemovesUrlsFromToolArgument(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $output = $provider->chat($this->buildTriggerInput(), 'gpt-test');

    $this->assertInstanceOf(ChatOutput::class, $output);

    // Message text is filtered.
    $this->assertStringNotContainsString(
      'href="https://evil.com/answer"',
      $output->getNormalized()->getText(),
    );

    // Tool argument is filtered (the existing behavior).
    $arg_value = $this->getFirstToolArgValue($output);
    $this->assertStringNotContainsString('href="https://evil.com/page"', $arg_value);
    $this->assertStringContainsString('here', $arg_value);
  }

  /**
   * Regression: a HostnameFilterDto with fullTrust=TRUE on ChatInput.
   *
   * Pre-fix, the DTO was applied only after the provider had already
   * constructed (and filtered) ToolsPropertyResult values, so the URL was
   * stripped despite fullTrust being requested.
   */
  public function testFullTrustDtoPreservesUrlsInToolArguments(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = $this->buildTriggerInput();
    $input->setHostnameFilter(new HostnameFilterDto(fullTrust: TRUE));

    $output = $provider->chat($input, 'gpt-test');

    // Tool argument keeps its URL.
    $arg_value = $this->getFirstToolArgValue($output);
    $this->assertStringContainsString(
      'href="https://evil.com/page"',
      $arg_value,
      'fullTrust DTO should bypass filtering for tool argument strings.',
    );

    // Message text also keeps its URL (already worked pre-fix; covered
    // here so a future regression in the inverse direction is caught).
    $this->assertStringContainsString(
      'href="https://evil.com/answer"',
      $output->getNormalized()->getText(),
    );
  }

  /**
   * Regression: a DTO applied to one call must not leak into the next.
   *
   * Two consecutive chat() invocations on the same request: the first
   * passes a fullTrust DTO, the second passes none. The second call must
   * see the original (default, filtering) behavior — the DTO from the
   * first call must not have stuck on the singleton HostnameFilter.
   */
  public function testDtoDoesNotSpillOverToSubsequentCall(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');

    // Call 1: with fullTrust DTO. URLs should survive.
    $input_one = $this->buildTriggerInput();
    $input_one->setHostnameFilter(new HostnameFilterDto(fullTrust: TRUE));
    $output_one = $provider->chat($input_one, 'gpt-test');
    $this->assertStringContainsString(
      'href="https://evil.com/page"',
      $this->getFirstToolArgValue($output_one),
    );

    // After call 1, the singleton's overrides for the DTO must be undone.
    // The pre-call snapshot was: allowedDomains=[], rewriteLinks=NULL,
    // fullTrust=NULL, plainTextMode=NULL — i.e. fullTrust is no longer in
    // effect. We assert the visible outcome rather than poking the
    // service's internals.
    $input_two = $this->buildTriggerInput();
    $output_two = $provider->chat($input_two, 'gpt-test');
    $this->assertStringNotContainsString(
      'href="https://evil.com/page"',
      $this->getFirstToolArgValue($output_two),
      'Second call without DTO must filter again — no spillover from call 1.',
    );
    $this->assertStringNotContainsString(
      'href="https://evil.com/answer"',
      $output_two->getNormalized()->getText(),
    );
  }

}
