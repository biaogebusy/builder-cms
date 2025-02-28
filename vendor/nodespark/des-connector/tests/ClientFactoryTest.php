<?php

namespace nodespark\DESConnector\Test;

use nodespark\DESConnector\ClientFactory;
use PHPUnit\Framework\TestCase;

/**
 * Class ClientFactoryTest
 *
 * @package nodespark\DESConnector\Test
 *
 * @coversDefaultClass \nodespark\DESConnector\ClientFactory
 */
class ClientFactoryTest extends TestCase {

  /**
   * @covers ::create
   */
  public function testCreate() {
    $client_factory = new ClientFactory();
    $instance = $client_factory->create(['hosts' => ['foo', 'bar']]);
    $this->assertInstanceOf('nodespark\DESConnector\Client', $instance);
  }

}
