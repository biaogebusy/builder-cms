<?php

namespace Drupal\ai\Service\AiProviderValidator;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\BasicRecursiveValidatorFactory;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\Plugin\ProviderProxy;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Provides a validator for an AI Provider.
 */
class AiProviderValidator implements AiProviderValidatorInterface {

  /**
   * The validator.
   *
   * @var \Symfony\Component\Validator\Validator\ValidatorInterface
   */
  protected ValidatorInterface $validator;

  /**
   * An extra list of constraints that can be added to the collection.
   *
   * @var \Symfony\Component\Validator\Constraint[]
   */
  protected array $extraConstraints;

  /**
   * Constructs a new AiProviderValidator instance.
   *
   * @param \Drupal\Core\Validation\BasicRecursiveValidatorFactory $validatorFactory
   *   The validator factory.
   */
  public function __construct(BasicRecursiveValidatorFactory $validatorFactory) {
    $this->validator = $validatorFactory->createValidator();
  }

  /**
   * {@inheritdoc}
   */
  public function validate(AiProviderInterface|ProviderProxy $provider, string $model, string $operationType, array $values): ConstraintViolationListInterface {
    $list = new ConstraintViolationList();

    $schema = $provider->getAvailableConfiguration($operationType, $model);
    if (empty($schema)) {
      return $list;
    }

    $constraints = $this->generateConstraints($schema);

    $violations = $this->validator->validate($values, $constraints);
    if ($violations->count() > 0) {
      $violations = new ConstraintViolationList(
        array_map(
          fn (ConstraintViolationInterface $violation) => $this->translateViolation($violation),
          (array) $violations->getIterator()
        )
      );
    }

    return $violations;
  }

  /**
   * Generate constraints based on the given schema.
   *
   * @param array $schema
   *   The schema of the AI Provider.
   *
   * @return \Symfony\Component\Validator\Constraint
   *   The nested constraints.
   */
  protected function generateConstraints(array $schema): Constraint {
    $fields = [];
    foreach ($schema as $key => $config) {
      $isRequired = FALSE;
      $constraints = [];

      // Constraint on primitive type.
      if (!empty($config['type'])) {
        $constraints[] = new Type($config['type']);
      }
      // Constraint on required.
      if (isset($config['required']) && $config['required'] === TRUE) {
        $constraints[] = new NotBlank();
        $isRequired = TRUE;
      }
      // Additional constraints.
      if (!empty($config['constraints'])) {
        // Constraint on min and max value.
        if (isset($config['constraints']['min']) || isset($config['constraints']['max'])) {
          $constraints[] = new Range([
            'min' => $config['constraints']['min'] ?? NULL,
            'max' => $config['constraints']['max'] ?? NULL,
          ]);
        }
        // Constraint on allowed string values.
        if (!empty($config['constraints']['options'])) {
          $constraints[] = new Choice([
            'choices' => $config['constraints']['options'],
          ]);
        }
      }

      $fields[$key] = $isRequired ?
        new Required(['constraints' => $constraints])
        : new Optional(['constraints' => $constraints]);
    }

    return new Collection([
      'fields' => $fields + $this->extraConstraints,
      'allowExtraFields' => FALSE,
    ]);
  }

  /**
   * Translate violation message for safe usage by Drupal.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationInterface $violation
   *   The violation.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationInterface
   *   The translated violation.
   */
  protected function translateViolation(ConstraintViolationInterface $violation): ConstraintViolationInterface {
    $message = $violation->getMessage();
    if (!$message instanceof TranslatableMarkup) {
      return $violation;
    }

    // Convert '{{ temperature }}' to '@temperature'.
    $string = preg_replace('/({{ (\w+) }})/', '@$2', $message->getUntranslatedString());
    // Convert translation arguments to safe arguments
    // eg. %temperature to @temperature.
    $arguments = array_combine(
      array_map(fn ($key) => str_replace('%', '@', $key), array_keys($message->getArguments())),
      array_values($message->getArguments()),
    );
    // phpcs:ignore
    $message = new TranslatableMarkup($string, $arguments, $message->getOptions());

    return new ConstraintViolation(
      $message,
      $violation->getMessageTemplate(),
      $violation->getParameters(),
      $violation->getRoot(),
      $violation->getPropertyPath(),
      $violation->getInvalidValue(),
      $violation->getPlural(),
      $violation->getCode(),
      $violation->getConstraint(),
      $violation->getCause()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function addConstraints(array $constraints): AiProviderValidatorInterface {
    $this->extraConstraints = $constraints;

    return $this;
  }

}
