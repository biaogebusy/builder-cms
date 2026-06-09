<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\OperationType;

use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\ai\OperationType\InputBase;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ai\OperationType\InputBase
 *
 * @group ai
 *
 * @see https://www.drupal.org/project/ai/issues/3584849
 */
class InputBaseGuardrailSetsTest extends UnitTestCase {

  /**
   * @covers ::addGuardrailSet
   * @covers ::getGuardrailSets
   */
  public function testAddGuardrailSetKeysById(): void {
    $input = $this->newInput();
    $a = $this->mockSet('a');
    $b = $this->mockSet('b');

    $input->addGuardrailSet($a);
    $input->addGuardrailSet($b);

    $sets = $input->getGuardrailSets();
    $this->assertSame(['a', 'b'], array_keys($sets));
    $this->assertSame([$a, $b], array_values($sets));
  }

  /**
   * @covers ::addGuardrailSet
   */
  public function testDuplicateIdReplacesInPlace(): void {
    $input = $this->newInput();
    $a1 = $this->mockSet('a');
    $a2 = $this->mockSet('a');

    $input->addGuardrailSet($a1);
    $input->addGuardrailSet($a2);

    $sets = $input->getGuardrailSets();
    $this->assertCount(1, $sets);
    $this->assertSame($a2, $sets['a']);
  }

  /**
   * @covers ::setGuardrailSets
   */
  public function testSetGuardrailSetsPreservesOrder(): void {
    $input = $this->newInput();
    $a = $this->mockSet('a');
    $b = $this->mockSet('b');
    $c = $this->mockSet('c');

    $input->setGuardrailSets([$a, $b, $c]);
    $this->assertSame(['a', 'b', 'c'], array_keys($input->getGuardrailSets()));
  }

  /**
   * @covers ::setGuardrailSets
   */
  public function testSetGuardrailSetsReplacesExisting(): void {
    $input = $this->newInput();
    $a = $this->mockSet('a');
    $b = $this->mockSet('b');
    $c = $this->mockSet('c');

    $input->addGuardrailSet($a);
    $input->addGuardrailSet($b);
    // Replace entirely — prior entries are gone, new ones are in list order.
    $input->setGuardrailSets([$c, $a]);

    $this->assertSame(['c', 'a'], array_keys($input->getGuardrailSets()));
  }

  /**
   * @covers ::setGuardrailSets
   */
  public function testSetGuardrailSetsAcceptsKeyedMap(): void {
    $input = $this->newInput();
    $a = $this->mockSet('a');
    $b = $this->mockSet('b');

    // Passing an already-keyed map works too; keys are ignored and re-derived
    // from each set's id().
    $input->setGuardrailSets(['ignored_key_1' => $a, 'ignored_key_2' => $b]);
    $this->assertSame(['a', 'b'], array_keys($input->getGuardrailSets()));
  }

  /**
   * @covers ::setGuardrailSet
   * @covers ::getGuardrailSet
   *
   * @group legacy
   */
  public function testLegacySetAndGetGuardrailSet(): void {
    $input = $this->newInput();
    $a = $this->mockSet('a');
    $b = $this->mockSet('b');

    // setGuardrailSet clears prior sets and stores only the new one.
    $input->addGuardrailSet($a);
    $this->expectDeprecation('Drupal\ai\OperationType\InputBase::setGuardrailSet() is deprecated in ai:1.4.0 and is removed from ai:2.0.0. Use ::addGuardrailSet() instead. See https://www.drupal.org/project/ai/issues/3584849');
    $input->setGuardrailSet($b);

    $this->assertSame(['b'], array_keys($input->getGuardrailSets()));

    $this->expectDeprecation('Drupal\ai\OperationType\InputBase::getGuardrailSet() is deprecated in ai:1.4.0 and is removed from ai:2.0.0. Use ::getGuardrailSets() instead. See https://www.drupal.org/project/ai/issues/3584849');
    $this->assertSame($b, $input->getGuardrailSet());
  }

  /**
   * @covers ::getGuardrailSet
   *
   * @group legacy
   */
  public function testLegacyGetGuardrailSetReturnsNullWhenEmpty(): void {
    $input = $this->newInput();
    $this->expectDeprecation('Drupal\ai\OperationType\InputBase::getGuardrailSet() is deprecated in ai:1.4.0 and is removed from ai:2.0.0. Use ::getGuardrailSets() instead. See https://www.drupal.org/project/ai/issues/3584849');
    $this->assertNull($input->getGuardrailSet());
  }

  /**
   * Returns a concrete anonymous InputBase for testing.
   */
  private function newInput(): InputBase {
    return new class extends InputBase {

      /**
       * {@inheritdoc}
       */
      public function toString(): string {
        return '';
      }

    };
  }

  /**
   * Builds a mocked AiGuardrailSetInterface with the given id().
   */
  private function mockSet(string $id): AiGuardrailSetInterface {
    $set = $this->createMock(AiGuardrailSetInterface::class);
    $set->method('id')->willReturn($id);
    return $set;
  }

}
