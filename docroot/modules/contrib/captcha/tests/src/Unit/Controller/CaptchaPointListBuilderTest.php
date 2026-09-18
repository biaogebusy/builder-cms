<?php

namespace Drupal\Tests\captcha\Unit\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\captcha\Entity\CaptchaPoint;
use Drupal\captcha\Entity\Controller\CaptchaPointListBuilder;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests for CaptchaPointListBuilder.
 *
 * @group captcha
 */
class CaptchaPointListBuilderTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Module handler mock.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface
   */
  protected $moduleHandler;

  /**
   * Container mock.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface
   */
  protected $mockContainer;

  /**
   * Entity type mock.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface
   */
  protected $mockEntityType;

  /**
   * Entity storage mock.
   *
   * @var \Prophecy\Prophecy\ProphecyInterface
   */
  protected $mockEntityStorage;

  /**
   * Captcha list builder.
   *
   * @var \Drupal\captcha\Entity\Controller\CaptchaPointListBuilder
   */
  protected $listBuilder;

  /**
   * Set up.
   */
  public function setUp(): void {
    parent::setUp();
    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);
    $this->moduleHandler->invokeAll(Argument::any(), Argument::any())->willReturn([]);
    $this->moduleHandler->alter(Argument::cetera())->willReturn([]);

    $this->mockContainer = $this->prophesize(ContainerInterface::class);
    $this->mockContainer->get('string_translation')->willReturn($this->getStringTranslationStub());
    $this->mockContainer->get('module_handler')->willReturn($this->moduleHandler->reveal());

    $this->mockEntityType = $this->prophesize(EntityTypeInterface::class);
    $this->mockEntityStorage = $this->prophesize(EntityStorageInterface::class);
    $this->listBuilder = new CaptchaPointListBuilder($this->mockEntityType->reveal(), $this->mockEntityStorage->reveal());

    \Drupal::setContainer($this->mockContainer->reveal());
  }

  /**
   * Test for buildHeader.
   */
  public function testBuildHeader() {
    $header = $this->listBuilder->buildHeader();
    $this->assertArrayHasKey('form_id', $header);
    $this->assertArrayHasKey('captcha_type', $header);
    $this->assertArrayHasKey('captcha_status', $header);
    $this->assertArrayHasKey('operations', $header);
  }

  /**
   * Test for buildRow.
   */
  public function testBuildRow() {
    $mockEntity = $this->prophesize(CaptchaPoint::class);
    $mockEntity->access(Argument::cetera())->willReturn(AccessResult::forbidden());
    $mockEntity->id()->willReturn('target_form_id');
    $mockEntity->label()->willReturn('target_form_id');
    $mockEntity->bundle()->willReturn('captcha_point');
    $mockEntity->uuid()->willReturn('test-uuid');
    $mockEntity->getEntityTypeId()->willReturn('captcha_point');
    $mockEntity->getCaptchaType()->willReturn('captcha_type');
    $mockEntity->status()->willReturn('captcha_status');
    $mockEntity->hasLinkTemplate('edit-form')->willReturn(FALSE);
    $mockEntity->hasLinkTemplate('delete-form')->willReturn(FALSE);

    $row = $this->listBuilder->buildRow($mockEntity->reveal());

    $this->assertArrayHasKey('form_id', $row);
    $this->assertEquals('target_form_id', $row['form_id']);

    $this->assertArrayHasKey('captcha_type', $row);
    $this->assertEquals('captcha_type', $row['captcha_type']);

    $this->assertArrayHasKey('captcha_status', $row);
    $this->assertEquals('Enabled', $row['captcha_status']);
  }

}
