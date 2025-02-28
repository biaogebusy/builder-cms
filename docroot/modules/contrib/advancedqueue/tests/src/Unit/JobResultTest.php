<?php

namespace Drupal\Tests\advancedqueue\Unit;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\advancedqueue\JobResult
 * @group advancedqueue
 */
class JobResultTest extends UnitTestCase {

  /**
   * @covers ::success
   * @covers ::getState
   * @covers ::getMessage
   */
  public function testSuccess() {
    $result = JobResult::success('We did it');
    $this->assertEquals(Job::STATE_SUCCESS, $result->getState());
    $this->assertEquals('We did it', $result->getMessage());
  }

  /**
   * @covers ::failure
   * @covers ::getState
   * @covers ::getMessage
   * @covers ::getMaxRetries
   * @covers ::getRetryDelay
   */
  public function testFailure() {
    $result = JobResult::failure('DB down');
    $this->assertEquals(Job::STATE_FAILURE, $result->getState());
    $this->assertEquals('DB down', $result->getMessage());
    $this->assertNull($result->getMaxRetries());
    $this->assertNull($result->getRetryDelay());
  }

  /**
   * @covers ::failure
   * @covers ::getState
   * @covers ::getMessage
   * @covers ::getMaxRetries
   * @covers ::getRetryDelay
   */
  public function testFailureWithRetryOverride() {
    $result = JobResult::failure('DB down', 2, 10);
    $this->assertEquals(Job::STATE_FAILURE, $result->getState());
    $this->assertEquals('DB down', $result->getMessage());
    $this->assertEquals(2, $result->getMaxRetries());
    $this->assertEquals(10, $result->getRetryDelay());
  }

  /**
   * @covers ::__construct
   * @covers ::getState
   * @covers ::getMessage
   * @covers ::getMaxRetries
   * @covers ::getRetryDelay
   */
  public function testConstructor() {
    $result = new JobResult(Job::STATE_FAILURE, 'Error message', 5);
    $this->assertEquals(Job::STATE_FAILURE, $result->getState());
    $this->assertEquals('Error message', $result->getMessage());
    $this->assertEquals(5, $result->getMaxRetries());
    $this->assertNull($result->getRetryDelay());
  }

}
