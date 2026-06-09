<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\OperationType\Chat;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\ai\Mock\MockIterator;
use Drupal\Tests\ai\Mock\MockStreamedChatIterator;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\RewriteOutputResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\Guardrail\StreamableGuardrailInterface;
use Drupal\ai\Service\HostnameFilter;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests streaming guardrail integration with StreamedChatMessageIterator.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\StreamedChatMessageIterator
 */
class StreamingGuardrailTest extends UnitTestCase {

  /**
   * Builds a configured MockStreamedChatIterator from a list of chunks.
   *
   * @param string[] $chunks
   *   The raw chunks the provider would stream.
   * @param int $expected_filter_calls
   *   How many times the hostname filter is expected to be called.
   *
   * @return \Drupal\Tests\ai\Mock\MockStreamedChatIterator
   *   The iterator wired up with mocked services.
   */
  private function buildIterator(array $chunks, int $expected_filter_calls = -1): MockStreamedChatIterator {
    $mock_event_dispatcher = $this->createMock(EventDispatcherInterface::class);
    $mock_event_dispatcher->method('dispatch');

    $iterator = new MockIterator($chunks);
    $message = new MockStreamedChatIterator($iterator);
    $message->setEventDispatcher($mock_event_dispatcher);

    $hostname_filter = $this->createMock(HostnameFilter::class);
    $filter_method = ($expected_filter_calls >= 0)
      ? $hostname_filter->expects($this->exactly($expected_filter_calls))->method('filterText')
      : $hostname_filter->method('filterText');
    $filter_method->willReturnCallback(fn($text) => $text);

    $container = new ContainerBuilder();
    $container->set('ai.hostname_filter_service', $hostname_filter);
    \Drupal::setContainer($container);

    return $message;
  }

  /**
   * Collects all non-empty text chunks yielded by an iterator.
   *
   * @param \Drupal\Tests\ai\Mock\MockStreamedChatIterator $iterator
   *   The iterator to consume.
   *
   * @return string[]
   *   Non-empty text chunks in order.
   */
  private function collectChunks(MockStreamedChatIterator $iterator): array {
    $parts = [];
    foreach ($iterator as $chunk) {
      if ($chunk->getText() !== '') {
        $parts[] = $chunk->getText();
      }
    }
    return $parts;
  }

  /**
   * Provides cases for testStreamingGuardrailBehavior().
   *
   * Each case supplies: input chunks, start/stop regexes, the guardrail result
   * type ('pass', 'stop', 'rewrite') and message, the expected buffer passed
   * to processStreamedBuffer(), and the expected concatenated output.
   *
   * Sentence boundary means a period (.) or newline (\n). Content before a
   * boundary is safe to yield; content after is held until the next boundary
   * or a start regex match.
   */
  public static function streamingGuardrailProvider(): array {
    return [
      'pass result releases buffer verbatim' => [
        'chunks' => ["safe\n", "SECRET_START\n", "SECRET_END\n", "safe\n"],
        'start_regex' => '/SECRET_START/',
        'stop_regex' => '/SECRET_END/',
        'result_type' => 'pass',
        'result_message' => '',
        'expected_buffer' => "SECRET_START\nSECRET_END\n",
        'expected_output' => "safe\nSECRET_START\nSECRET_END\nsafe\n",
      ],
      'stop result suppresses buffer and yields message' => [
        'chunks' => ["safe\n", "BAD_START\n", "BAD_END\n"],
        'start_regex' => '/BAD_START/',
        'stop_regex' => '/BAD_END/',
        'result_type' => 'stop',
        'result_message' => 'Content blocked by guardrail.',
        'expected_buffer' => "BAD_START\nBAD_END\n",
        'expected_output' => "safe\nContent blocked by guardrail.",
      ],
      'rewrite result replaces buffer with message' => [
        'chunks' => ["ok\n", "REWRITE_ME\n", "STOP\n"],
        'start_regex' => '/REWRITE_ME/',
        'stop_regex' => '/STOP/',
        'result_type' => 'rewrite',
        'result_message' => '[rewritten content]',
        'expected_buffer' => "REWRITE_ME\nSTOP\n",
        'expected_output' => "ok\n[rewritten content]",
      ],
      'end of stream flushes active guardrail buffer' => [
        'chunks' => ["safe\n", "SECRET_DATA\n"],
        'start_regex' => '/SECRET_DATA/',
        'stop_regex' => '/NEVER_MATCHES_XYZ/',
        'result_type' => 'stop',
        'result_message' => 'Blocked at end of stream.',
        'expected_buffer' => "SECRET_DATA\n",
        'expected_output' => "safe\nBlocked at end of stream.",
      ],
      'empty start regex buffers from first chunk' => [
        'chunks' => ["first\n", "second\n"],
        'start_regex' => '',
        'stop_regex' => '/second/',
        'result_type' => 'stop',
        'result_message' => 'All content blocked.',
        'expected_buffer' => "first\nsecond\n",
        'expected_output' => 'All content blocked.',
      ],
      'content before start regex is yielded normally if contains newline' => [
        'chunks' => ["before\n", "trigger\n", "after_trigger\n"],
        'start_regex' => '/trigger/',
        'stop_regex' => '/after_trigger/',
        'result_type' => 'stop',
        'result_message' => 'Blocked.',
        'expected_buffer' => "trigger\nafter_trigger\n",
        'expected_output' => "before\nBlocked.",
      ],
      'content before start regex is yielded normally if contains period' => [
        // The URL-safety buffer only flushes on \n or when it reaches 100
        // characters. The first chunk is padded to exactly 100 chars to force
        // a size-based flush before "Drupal" arrives. Chunks 2 and 3 have no
        // \n and are small, so the URL-safety buffer concatenates them before
        // the guardrail sees them — both end up in the guardrail buffer. This
        // is expected: the framework hands the full captured content to the
        // plugin, which then decides what to do with it.
        'chunks' => [
          str_repeat('a', 94) . " done.",
          "Drupal is a powerful CMS.",
          " It has many modules.",
        ],
        'start_regex' => '/Drupal/',
        'stop_regex' => '/CMS/',
        'result_type' => 'stop',
        'result_message' => 'Blocked.',
        'expected_buffer' => "Drupal is a powerful CMS. It has many modules.",
        'expected_output' => str_repeat('a', 94) . " done." . 'Blocked.',
      ],
      'content between start and stop tags is buffered and rewritten correctly when each chunk exceeds URL-safety buffer size' => [
        'chunks' => [
          "When comparing content management systems, it is important to consider scalability, community support, and ease of use.",
          "<start>WordPress is the most widely used CMS in the world, powering millions of websites across various industries.",
          "<stop>WordPress communities contribute plugins, themes, and documentation to help users build and maintain their sites.",
          "Drupal and Joomla are also popular choices, offering enterprise-level features and strong community backing for large projects.",
        ],
        'start_regex' => '/<start>/',
        'stop_regex' => '/<stop>/',
        'result_type' => 'rewrite',
        'result_message' => 'Content about [Another popular CMS] has been rewritten by the guardrail.',
        'expected_buffer' => "<start>WordPress is the most widely used CMS in the world, powering millions of websites across various industries.<stop>WordPress communities contribute plugins, themes, and documentation to help users build and maintain their sites.",
        'expected_output' => "When comparing content management systems, it is important to consider scalability, community support, and ease of use.Content about [Another popular CMS] has been rewritten by the guardrail.Drupal and Joomla are also popular choices, offering enterprise-level features and strong community backing for large projects.",
      ],
      'subsequent chunk is also processed by guardrail if start and stop regexes are matched in the same chunk when the chunk exceeds URL-safety buffer size' => [
        'chunks' => [
          "When comparing content management systems, it is important to consider scalability, community support, and ease of use.",
          "<start>WordPress<stop> is the most widely used CMS in the world, powering millions of websites across various industries.",
          "WordPress communities contribute plugins, themes, and documentation to help users build and maintain their sites.",
          "Drupal and Joomla are also popular choices, offering enterprise-level features and strong community backing for large projects.",
        ],
        'start_regex' => '/<start>/',
        'stop_regex' => '/<stop>/',
        'result_type' => 'rewrite',
        'result_message' => 'Content about [Another popular CMS] has been rewritten by the guardrail.',
        'expected_buffer' => "<start>WordPress<stop> is the most widely used CMS in the world, powering millions of websites across various industries.WordPress communities contribute plugins, themes, and documentation to help users build and maintain their sites.",
        'expected_output' => "When comparing content management systems, it is important to consider scalability, community support, and ease of use.Content about [Another popular CMS] has been rewritten by the guardrail.Drupal and Joomla are also popular choices, offering enterprise-level features and strong community backing for large projects.",
      ],
    ];
  }

  /**
   * Tests streaming guardrail behavior across various scenarios.
   *
   * @dataProvider streamingGuardrailProvider
   */
  public function testStreamingGuardrailBehavior(
    array $chunks,
    string $start_regex,
    string $stop_regex,
    string $result_type,
    string $result_message,
    string $expected_buffer,
    string $expected_output,
  ): void {
    $iterator = $this->buildIterator($chunks);

    $seen_buffers = [];
    $guardrail = $this->createMock(StreamableGuardrailInterface::class);
    $guardrail->method('getStartRegex')->willReturn($start_regex);
    $guardrail->method('getStopRegex')->willReturn($stop_regex);
    $guardrail->method('processStreamedBuffer')
      ->willReturnCallback(function (string $buf) use (&$seen_buffers, $guardrail, $result_type, $result_message) {
        $seen_buffers[] = $buf;
        return match($result_type) {
          'pass' => new PassResult($buf, $guardrail),
          'stop' => new StopResult($result_message, $guardrail),
          'rewrite' => new RewriteOutputResult($result_message, $guardrail),
        };
      });
    $iterator->addStreamingGuardrail($guardrail);

    $parts = $this->collectChunks($iterator);

    // The buffer passed to processStreamedBuffer must equal the accumulated
    // content between start and stop regex matches (spanning chunks).
    $this->assertSame([$expected_buffer], $seen_buffers);
    // The concatenated output must match exactly. The iterator may re-split
    // released text into word-level tokens, so we compare on concatenation.
    $this->assertSame($expected_output, implode('', $parts));
  }

  /**
   * No guardrails: stream passes through unchanged.
   */
  public function testNoGuardrailsStreamPassesThrough(): void {
    $chunks = ['Hello ', "world\n", 'here.'];
    $iterator = $this->buildIterator($chunks);

    $parts = $this->collectChunks($iterator);
    $this->assertSame(implode('', $chunks), implode('', $parts));
  }

  /**
   * Tests that getStreamingGuardrails() returns registered guardrails.
   */
  public function testGetStreamingGuardrails(): void {
    $iterator = $this->buildIterator(["test\n"]);
    $guardrail = $this->createMock(StreamableGuardrailInterface::class);
    $guardrail->method('getStartRegex')->willReturn('/START/');
    $guardrail->method('getStopRegex')->willReturn('/END/');
    $guardrail->method('processStreamedBuffer')->willReturnCallback(
      fn(string $buf) => new PassResult($buf, $guardrail)
    );
    $iterator->addStreamingGuardrail($guardrail);

    $this->assertCount(1, $iterator->getStreamingGuardrails());
    $this->assertSame($guardrail, $iterator->getStreamingGuardrails()[0]);
  }

  /**
   * Tests that the start tag split across chunks is correctly detected.
   *
   * @cspell:disable
   */
  public function testStartTagSplitAcrossChunksWithCmsContent(): void {
    $chunks = [
      'Popular open source CMS platforms like WordPress, Drupal, and Joomla differ significantly in flexibility, ease of use, and customization capabilities, making the choice highly dependent on the specific requirements and long term goals of a project. WordPress is widely known for its beginner',
      'friendly interface, vast plugin ecosystem, and quick setup process, which makes it suitable for blogs and small to medium websites, while Drupal offers a more structured and powerful system designed for complex, large scale, and highly customized applications. Joomla provides a middle ground by offering more built in flexibility than WordPress while being less complex than Drupal, which allows developers to build moderately advanced websites without the steep learning curve often associated with <sta',
      'rt> Drupal<stop>.From a developer perspective, Drupal is often preferred for its strong API first approach and fine grained control over content structures, whereas WordPress focuses more on ease of extension through plugins and themes rather than deep architectural control.Ultim',
      'ately, the decision between these platforms depends on factors like scalability, performance requirements, development expertise, and the need for customization, as each CMS serves a distinct audience despite sharing the same open source philosophy. In addition,',
      ' factors such as community support, availability of skilled developers, long term maintenance costs, and the frequency of security updates also play a crucial role in determining which CMS is the most practical and sustainable choice for a given project.',
    ];

    // Subclass the iterator to record what processStreamingGuardrails()
    // returns for each chunk, so we can verify the returned text is correct.
    $mock_event_dispatcher = $this->createMock(EventDispatcherInterface::class);
    $mock_event_dispatcher->method('dispatch');
    $hostname_filter = $this->createMock(HostnameFilter::class);
    $hostname_filter->method('filterText')->willReturnCallback(fn($text) => $text);
    $container = new ContainerBuilder();
    $container->set('ai.hostname_filter_service', $hostname_filter);
    \Drupal::setContainer($container);

    $iterator = new class(new MockIterator($chunks)) extends MockStreamedChatIterator {
      /**
       * Records (input, output) pairs from processStreamingGuardrails calls.
       *
       * @var array
       */
      public array $guardrailCalls = [];

      /**
       * Records the input and output of each processStreamingGuardrails call.
       */
      protected function processStreamingGuardrails(string $text): string {
        $out = parent::processStreamingGuardrails($text);
        $this->guardrailCalls[] = ['in' => $text, 'out' => $out];
        return $out;
      }

    };
    $iterator->setEventDispatcher($mock_event_dispatcher);

    $guardrail = $this->createMock(StreamableGuardrailInterface::class);
    $guardrail->method('getStartRegex')->willReturn('/<start>/');
    $guardrail->method('getStopRegex')->willReturn('/<stop>/');
    $guardrail->method('processStreamedBuffer')
      ->willReturnCallback(fn(string $buf) => new PassResult($buf, $guardrail));
    $iterator->addStreamingGuardrail($guardrail);

    // Drive the iterator to completion.
    iterator_to_array($iterator, FALSE);

    $this->assertSame([
      [
        'in' => 'Popular open source CMS platforms like WordPress, Drupal, and Joomla differ significantly in flexibility, ease of use, and customization capabilities, making the choice highly dependent on the specific requirements and long term goals of a project. WordPress is widely known for its beginner',
        'out' => 'Popular open source CMS platforms like WordPress, Drupal, and Joomla differ significantly in flexibility, ease of use, and customization capabilities, making the choice highly dependent on the specific requirements and long term goals of a project.',
      ],
      [
        'in' => 'friendly interface, vast plugin ecosystem, and quick setup process, which makes it suitable for blogs and small to medium websites, while Drupal offers a more structured and powerful system designed for complex, large scale, and highly customized applications. Joomla provides a middle ground by offering more built in flexibility than WordPress while being less complex than Drupal, which allows developers to build moderately advanced websites without the steep learning curve often associated with <sta',
        'out' => ' WordPress is widely known for its beginnerfriendly interface, vast plugin ecosystem, and quick setup process, which makes it suitable for blogs and small to medium websites, while Drupal offers a more structured and powerful system designed for complex, large scale, and highly customized applications.',
      ],
      [
        // '<sta' + 'rt>' from chunks completes '<start>', and '<stop>' also
        // appears here. Guardrail is active and buffering — nothing output
        // until next chunk flushes buffer.
        'in' => 'rt> Drupal<stop>.From a developer perspective, Drupal is often preferred for its strong API first approach and fine grained control over content structures, whereas WordPress focuses more on ease of extension through plugins and themes rather than deep architectural control.Ultim',
        'out' => '',
      ],
      [
        'in' => 'ately, the decision between these platforms depends on factors like scalability, performance requirements, development expertise, and the need for customization, as each CMS serves a distinct audience despite sharing the same open source philosophy. In addition,',
        'out' => ' Joomla provides a middle ground by offering more built in flexibility than WordPress while being less complex than Drupal, which allows developers to build moderately advanced websites without the steep learning curve often associated with <start> Drupal<stop>.From a developer perspective, Drupal is often preferred for its strong API first approach and fine grained control over content structures, whereas WordPress focuses more on ease of extension through plugins and themes rather than deep architectural control.Ultimately, the decision between these platforms depends on factors like scalability, performance requirements, development expertise, and the need for customization, as each CMS serves a distinct audience despite sharing the same open source philosophy. In addition,',
      ],
      [
        'in' => ' factors such as community support, availability of skilled developers, long term maintenance costs, and the frequency of security updates also play a crucial role in determining which CMS is the most practical and sustainable choice for a given project.',
        'out' => ' factors such as community support, availability of skilled developers, long term maintenance costs, and the frequency of security updates also play a crucial role in determining which CMS is the most practical and sustainable choice for a given project.',
      ],
    ], $iterator->guardrailCalls);
  }

  /**
   * Tests buffered content is yielded at stream end without start regex match.
   */
  public function testBufferedContentIsYieldedAtEndOfStreamWhenStartRegexNeverMatched(): void {
    // No period or newline in either chunk, so no sentence boundary is found
    // to flush the buffer during streaming. The start regex never matches, so
    // the guardrail stays inactive. At end-of-stream the buffer is yielded
    // as-is to prevent data loss.
    $chunks = ["Hello world", " no boundary here"];

    $iterator = $this->buildIterator($chunks);

    $guardrail = $this->createMock(StreamableGuardrailInterface::class);
    $guardrail->method('getStartRegex')->willReturn('/NEVER_MATCHES/');
    $guardrail->method('getStopRegex')->willReturn('/NEVER_MATCHES/');
    $guardrail->method('processStreamedBuffer')
      ->willReturnCallback(fn(string $buf) => new PassResult($buf, $guardrail));
    $iterator->addStreamingGuardrail($guardrail);

    $parts = $this->collectChunks($iterator);
    $output = implode('', $parts);

    $this->assertSame('Hello world no boundary here', $output);
  }

  /**
   * Tests that exceeding maxGuardrailBufferSize force-evaluates the buffer.
   */
  public function testMaxGuardrailBufferSizeForceEvaluatesBuffer(): void {
    // Set a tiny max buffer so it trips after the first buffered chunk.
    $chunks = ["START_MARKER\n", str_repeat('x', 20) . "\n", "more\n"];
    $iterator = $this->buildIterator($chunks);
    $iterator->setMaxGuardrailBufferSize(10);

    $guardrail = $this->createMock(StreamableGuardrailInterface::class);
    $guardrail->method('getStartRegex')->willReturn('/START_MARKER/');
    // Stop regex that will never match — force-flush must fire instead.
    $guardrail->method('getStopRegex')->willReturn('/NEVER_MATCHES_XYZ/');
    $guardrail->method('processStreamedBuffer')
      ->willReturn(new StopResult('Force-flushed.', $guardrail));
    $iterator->addStreamingGuardrail($guardrail);

    $parts = $this->collectChunks($iterator);
    $full = implode('', $parts);

    $this->assertStringNotContainsString('START_MARKER', $full);
    $this->assertStringContainsString('Force-flushed.', $full);
  }

}
