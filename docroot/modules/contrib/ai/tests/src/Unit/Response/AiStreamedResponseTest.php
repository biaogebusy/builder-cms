<?php

namespace Drupal\Tests\ai\Unit\Response;

use Drupal\ai\Response\AiStreamedResponse;
use PHPUnit\Framework\TestCase;

/**
 * Tests that AiStreamedResponse sets correct default headers.
 *
 * @group ai
 * @covers \Drupal\ai\Response\AiStreamedResponse
 */
class AiStreamedResponseTest extends TestCase {

  /**
   * Tests that default headers are set correctly.
   */
  public function testDefaultHeaders(): void {
    $response = new AiStreamedResponse(function () {
      echo 'test';
    });

    $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));
    $this->assertEquals('no-store', $response->headers->get('Surrogate-Control'));
    $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    $this->assertEquals('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
  }

  /**
   * Tests that caller-provided headers override defaults.
   */
  public function testCustomHeadersOverrideDefaults(): void {
    $response = new AiStreamedResponse(function () {
      echo 'test';
    }, 200, [
      'Content-Type' => 'text/event-stream',
    ]);

    // Custom header overrides the default.
    $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
    // Other defaults are still present.
    $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));
    $this->assertEquals('no-store', $response->headers->get('Surrogate-Control'));
    $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
  }

  /**
   * Tests that setCallback preserves headers.
   */
  public function testSetCallbackPreservesHeaders(): void {
    $response = new AiStreamedResponse();
    $response->setCallback(function () {
      echo 'test';
    });

    $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));
    $this->assertEquals('no-store', $response->headers->get('Surrogate-Control'));
    $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    $this->assertEquals('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
  }

  /**
   * Tests that sendContent clears output buffers before invoking callback.
   */
  public function testSendContentClearsOutputBuffers(): void {
    $ob_level_in_callback = NULL;
    $response = new AiStreamedResponse(function () use (&$ob_level_in_callback) {
      $ob_level_in_callback = ob_get_level();
    });

    // Remember PHPUnit's OB level so we can restore it after.
    $phpunit_level = ob_get_level();

    // Simulate Drupal/PHP output buffers that exist before streaming.
    ob_start();
    ob_start();

    $response->sendContent();

    // Restore PHPUnit's output buffers that were cleared by sendContent.
    while (ob_get_level() < $phpunit_level) {
      ob_start();
    }

    $this->assertEquals(0, $ob_level_in_callback);
  }

}
