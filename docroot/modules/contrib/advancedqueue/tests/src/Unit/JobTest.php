<?php

namespace Drupal\Tests\advancedqueue\Unit;

use Drupal\advancedqueue\Job;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\advancedqueue\Job
 * @group advancedqueue
 */
class JobTest extends UnitTestCase {

  /**
   * @covers ::create
   * @covers ::getType
   * @covers ::getPayload
   * @covers ::getState
   */
  public function testCreate() {
    $job = Job::create('test', ['my' => 'data']);
    $this->assertEquals('test', $job->getType());
    $this->assertEquals(['my' => 'data'], $job->getPayload());
    $this->assertEquals(Job::STATE_QUEUED, $job->getState());
  }

  /**
   * @covers ::__construct
   */
  public function testIncompleteDefinition() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Missing property "type"');
    new Job([
      'payload' => ['123456'],
      'state' => Job::STATE_QUEUED,
    ]);
  }

  /**
   * @covers ::__construct
   */
  public function testInvalidState() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid state "INVALID" given');
    new Job([
      'type' => 'test',
      'payload' => ['123456'],
      'state' => 'INVALID',
    ]);
  }

  /**
   * @covers ::__construct
   * @covers ::toArray
   * @covers ::getId
   * @covers ::setId
   * @covers ::getQueueId
   * @covers ::setQueueId
   * @covers ::getType
   * @covers ::setType
   * @covers ::getPayload
   * @covers ::setPayload
   * @covers ::getState
   * @covers ::setState
   * @covers ::getMessage
   * @covers ::setMessage
   * @covers ::getNumRetries
   * @covers ::setNumRetries
   * @covers ::getAvailableTime
   * @covers ::setAvailableTime
   * @covers ::getProcessedTime
   * @covers ::setProcessedTime
   * @covers ::getExpiresTime
   * @covers ::setExpiresTime
   */
  public function testConstructor() {
    $job_definition = [
      'id' => 1,
      'queue_id' => 'default',
      'type' => 'test',
      'payload' => ['my' => 'data'],
      'state' => Job::STATE_QUEUED,
      'available' => 1509018584,
      'expires' => 1509018589,
      // A queued job wouldn't have these two set, but we need it for the test.
      'message' => 'Test',
      'processed' => 1509018586,
    ];
    $job = new Job($job_definition);

    $this->assertEquals($job_definition, $job->toArray());

    $this->assertEquals($job_definition['id'], $job->getId());
    $job->setId('2');
    $this->assertEquals('2', $job->getId());

    $this->assertEquals($job_definition['queue_id'], $job->getQueueId());
    $job->setQueueId('high_priority');
    $this->assertEquals('high_priority', $job->getQueueId());

    $this->assertEquals($job_definition['type'], $job->getType());
    $job->setType('test2');
    $this->assertEquals('test2', $job->getType());

    $this->assertEquals($job_definition['payload'], $job->getPayload());
    $job->setPayload(['data2']);
    $this->assertEquals(['data2'], $job->getPayload());

    $this->assertEquals($job_definition['state'], $job->getState());
    $job->setState(Job::STATE_PROCESSING);
    $this->assertEquals(Job::STATE_PROCESSING, $job->getState());

    $this->assertEquals($job_definition['message'], $job->getMessage());
    $job->setMessage('Test!');
    $this->assertEquals('Test!', $job->getMessage());

    $this->assertEquals(0, $job->getNumRetries());
    $job->setNumRetries(4);
    $this->assertEquals(4, $job->getNumRetries());

    $this->assertEquals($job_definition['available'], $job->getAvailableTime());
    $job->setAvailableTime(1509018580);
    $this->assertEquals(1509018580, $job->getAvailableTime());

    $this->assertEquals($job_definition['processed'], $job->getProcessedTime());
    $job->setProcessedTime(1509018600);
    $this->assertEquals(1509018600, $job->getProcessedTime());

    $this->assertEquals($job_definition['expires'], $job->getExpiresTime());
    $job->setExpiresTime(1509018585);
    $this->assertEquals(1509018585, $job->getExpiresTime());

    // Confirm that changing the state from processing resets the expires time.
    $job->setState(Job::STATE_SUCCESS);
    $this->assertEmpty($job->getExpiresTime());
  }

}
