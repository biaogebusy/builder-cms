<?php

namespace Drupal\Tests\simple_oauth\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\simple_oauth\Plugin\Validation\Constraint\Oauth2RedirectUri;
use Drupal\simple_oauth\Plugin\Validation\Constraint\Oauth2RedirectUriValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @coversDefaultClass \Drupal\simple_oauth\Plugin\Validation\Constraint\Oauth2RedirectUriValidator
 * @group simple_oauth
 */
class Oauth2RedirectUriValidatorTest extends UnitTestCase {

  /**
   * @covers ::validate
   * @dataProvider providerValidate
   */
  public function testValidate($value, $valid) {
    $constraint = new Oauth2RedirectUri();
    $validator = new Oauth2RedirectUriValidator();
    $context = $this->createMock(ExecutionContextInterface::class);

    if ($valid) {
      $context->expects($this->never())
        ->method('addViolation');
    }
    else {
      $context->expects($this->once())
        ->method('addViolation');
    }

    $items = $this->createMock('Drupal\Core\Field\FieldItemListInterface');
    $items->expects($this->once())
      ->method('getValue')
      ->willReturn([['value' => $value]]);

    $validator->initialize($context);
    $validator->validate($items, $constraint);
  }

  /**
   * Data provider for ::testValidate.
   */
  public static function providerValidate(): array {
    return [
      ['http://localhost', TRUE],
      ['https://test', TRUE],
      ['mobile://test', TRUE],
      ['http://127.0.0.1', TRUE],
      ['com.example.app://test', TRUE],
      ['web+ap://example.com', TRUE],
      ['test.test//test', FALSE],
      ['test/test//test', FALSE],
      ['www.test.com', FALSE],
      ['test.com', FALSE],
    ];
  }

}
