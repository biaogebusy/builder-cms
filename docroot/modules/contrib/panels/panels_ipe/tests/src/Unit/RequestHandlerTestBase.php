<?php

namespace Drupal\Tests\panels_ipe\Unit;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;
use Drupal\panels\Storage\PanelsStorageManagerInterface;
use Drupal\Core\TempStore\SharedTempStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base tests for IPE request handler classes.
 */
abstract class RequestHandlerTestBase extends TestCase {

  /**
   * @var  \Drupal\panels_ipe\Helpers\RequestHandlerInterface */
  protected $sut;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject */
  protected $moduleHandler;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject */
  protected $panelsStore;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject */
  protected $tempStore;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject */
  protected $panelsDisplay;

  /**
   *
   */
  public function setUp(): void {
    parent::setUp();
    $this->moduleHandler = $this->getMockForAbstractClass(ModuleHandlerInterface::class);
    $this->panelsStore = $this->getMockForAbstractClass(PanelsStorageManagerInterface::class);
    $this->tempStore = $this->createMock(SharedTempStore::class);

    $this->panelsDisplay = $this->createMock(PanelsDisplayVariant::class);
  }

  protected function createRequest($content = NULL) {
    return new Request([], [], [], [], [], [], $content);
  }

  /**
   * @test
   */
  public function emptyRequestResultsInFailedResponse() {
    $this->sut->handleRequest($this->panelsDisplay, $this->createRequest());

    $expected = new JsonResponse(['success' => FALSE], 400);
    $this->assertEquals($expected, $this->sut->getJsonResponse());
  }

}
