<?php

namespace Drupal\Tests\metatag\Unit;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\Tests\UnitTestCase;

/**
 * This class provides methods for testing the MetaNameBase class.
 *
 * @group metatag
 */
class MetaNameBaseTest extends UnitTestCase {

  /**
   * The MetaNameBase Mocked Object.
   *
   * @var \Drupal\metatag\Plugin\metatag\Tag\MetaNameBase|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $metaNameBase;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mocking cause it's an abstract class.
    $this->metaNameBase = $this->getMockBuilder(MetaNameBase::class)
      ->setConstructorArgs([[], 'test', []])
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();
  }

  /**
   * Tests the tidy method.
   */
  public function testTidy() {
    $method = "tidy";
    $class = new \ReflectionClass(get_class($this->metaNameBase));
    $method = $class->getMethod($method);
    // Set the protected method tidy to be accessible.
    $method->setAccessible(TRUE);

    $filterResult1 = $method->invoke($this->metaNameBase, "  Test   123  ");
    $this->assertEquals('Test 123', $filterResult1);
    $filterResult2 = $method->invoke($this->metaNameBase, '  Test   123    Test');
    $this->assertEquals('Test 123 Test', $filterResult2);
    $filterResult3 = $method->invoke(
        $this->metaNameBase,
        "Test \n\n123\n  Test  \n  "
      );
    $this->assertEquals('Test 123 Test', $filterResult3);
    $filterResult4 = $method->invoke(
        $this->metaNameBase,
        "Test \r\n\r\n 123  \r\n "
      );
    $this->assertEquals('Test 123', $filterResult4);
    $filterResult5 = $method->invoke(
        $this->metaNameBase,
        "Test \t\t123  \tTest"
      );
    $this->assertEquals('Test 123 Test', $filterResult5);
  }

  /**
   * Tests the tidy method with UTF-8 characters.
   *
   * Specifically tests that accented characters like 'à' are preserved
   * correctly and don't get corrupted during the preg_replace operation.
   * This addresses issues with JSON API encoding crashes.
   */
  public function testTidyUtf8Characters() {
    $method = "tidy";
    $class = new \ReflectionClass(get_class($this->metaNameBase));
    $method = $class->getMethod($method);
    // Set the protected method tidy to be accessible.
    $method->setAccessible(TRUE);

    // Test French accented characters
    $filterResult1 = $method->invoke($this->metaNameBase, "  Café   à   Paris  ");
    $this->assertEquals('Café à Paris', $filterResult1);

    // Test German umlauts
    $filterResult2 = $method->invoke($this->metaNameBase, "  Müller   Schöne   Größe  ");
    $this->assertEquals('Müller Schöne Größe', $filterResult2);

    // Test Spanish characters
    $filterResult3 = $method->invoke($this->metaNameBase, "  Niño   Mañana   España  ");
    $this->assertEquals('Niño Mañana España', $filterResult3);

    // Test mixed UTF-8 with line breaks and tabs
    $filterResult4 = $method->invoke(
      $this->metaNameBase,
      "Résumé \n\n für  \t große   Rêve"
    );
    $this->assertEquals('Résumé für große Rêve', $filterResult4);

    // Test emoji and other Unicode characters
    $filterResult5 = $method->invoke($this->metaNameBase, "  Test   🚀   Rocket  ");
    $this->assertEquals('Test 🚀 Rocket', $filterResult5);

    // Test the specific case that was causing JSON API crashes
    $filterResult6 = $method->invoke($this->metaNameBase, "Content   with   à   character");
    $this->assertEquals('Content with à character', $filterResult6);

    // Verify the result can be JSON encoded without error
    $jsonResult = json_encode($filterResult6);
    $this->assertNotFalse($jsonResult, 'UTF-8 content should be JSON encodable');
    $this->assertEquals('"Content with \u00e0 character"', $jsonResult);
  }

}
