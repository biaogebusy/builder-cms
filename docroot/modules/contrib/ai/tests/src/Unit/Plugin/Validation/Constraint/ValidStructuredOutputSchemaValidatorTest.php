<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Drupal\ai\Plugin\Validation\Constraint\ValidStructuredOutputSchema;
use Drupal\ai\Plugin\Validation\Constraint\ValidStructuredOutputSchemaValidator;
use Drupal\ai\Service\AiValidatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests the ValidStructuredOutputSchemaValidator.
 *
 * @group ai
 * @coversDefaultClass \Drupal\ai\Plugin\Validation\Constraint\ValidStructuredOutputSchemaValidator
 */
class ValidStructuredOutputSchemaValidatorTest extends TestCase {

  /**
   * The validator instance.
   *
   * @var \Drupal\ai\Plugin\Validation\Constraint\ValidStructuredOutputSchemaValidator
   */
  protected ValidStructuredOutputSchemaValidator $validator;

  /**
   * The constraint.
   *
   * @var \Drupal\ai\Plugin\Validation\Constraint\ValidStructuredOutputSchema
   */
  protected ValidStructuredOutputSchema $constraint;

  /**
   * The execution context.
   *
   * @var \Symfony\Component\Validator\Context\ExecutionContextInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $context;

  /**
   * The AI validator service.
   *
   * @var \Drupal\ai\Service\AiValidatorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $aiValidator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->aiValidator = $this->createMock(AiValidatorInterface::class);
    $this->validator = new ValidStructuredOutputSchemaValidator($this->aiValidator);
    $this->constraint = new ValidStructuredOutputSchema();
    $this->context = $this->createMock(ExecutionContextInterface::class);
    $this->validator->initialize($this->context);
  }

  /**
   * Tests validation with valid schema.
   */
  public function testValidSchema(): void {
    $value = [
      'name' => 'test_schema',
      'json_schema' => [
        'type' => 'object',
        'properties' => ['field' => ['type' => 'string']],
        'required' => ['field'],
      ],
    ];

    $this->aiValidator->expects($this->once())
      ->method('validateStructuredOutputSchema')
      ->with($value)
      ->willReturn([]);

    $this->context->expects($this->never())
      ->method('buildViolation');

    $this->validator->validate($value, $this->constraint);
  }

  /**
   * Tests validation with invalid schema.
   */
  public function testInvalidSchema(): void {
    $value = [
      'name' => 'Invalid Name',
      'json_schema' => [],
    ];

    $errors = ['Schema name must contain only lowercase letters'];

    $this->aiValidator->expects($this->once())
      ->method('validateStructuredOutputSchema')
      ->with($value)
      ->willReturn($errors);

    $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $builder->expects($this->once())
      ->method('setParameter')
      ->with('@errors', 'Schema name must contain only lowercase letters')
      ->willReturnSelf();
    $builder->expects($this->once())
      ->method('addViolation');

    $this->context->expects($this->once())
      ->method('buildViolation')
      ->with($this->constraint->message)
      ->willReturn($builder);

    $this->validator->validate($value, $this->constraint);
  }

  /**
   * Tests validation with empty value.
   */
  public function testEmptyValue(): void {
    $this->aiValidator->expects($this->never())
      ->method('validateStructuredOutputSchema');

    $this->context->expects($this->never())
      ->method('buildViolation');

    $this->validator->validate([], $this->constraint);
  }

  /**
   * Tests validation with null value.
   */
  public function testNullValue(): void {
    $this->aiValidator->expects($this->never())
      ->method('validateStructuredOutputSchema');

    $this->context->expects($this->never())
      ->method('buildViolation');

    $this->validator->validate(NULL, $this->constraint);
  }

}
