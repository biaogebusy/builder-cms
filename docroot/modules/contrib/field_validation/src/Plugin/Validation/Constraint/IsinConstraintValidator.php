<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Isin;
use Symfony\Component\Validator\Constraints\IsinValidator;

/**
 * Isin constraint.
 *
 */
final class IsinConstraintValidator extends IsinValidator {

    /**
     * @return void
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Isin) {
            throw new UnexpectedTypeException($constraint, Isin::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = strtoupper($value);

        if (Isin::VALIDATION_LENGTH !== \strlen($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Isin::INVALID_LENGTH_ERROR)
                ->addViolation();

            return;
        }

        if (!preg_match(Isin::VALIDATION_PATTERN, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Isin::INVALID_PATTERN_ERROR)
                ->addViolation();

            return;
        }

        if (!$this->isCorrectChecksum($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Isin::INVALID_CHECKSUM_ERROR)
                ->addViolation();
        }
    }
    //copy the logic from https://github.com/djmarland/isin,
	//symfony isin use  $this->context->getValidator()->validate($number, new Luhn())
	//which get fatal error in drupal. Drupal use typed data validation.
    private function isCorrectChecksum($input)
    {
        $characters = str_split($input);
        // convert all characters to numbers (ints)
        foreach ($characters as $i => $char) {
            // cast to int, by using intval at base 36 we also convert letters to numbers
            $characters[$i] = intval($char, 36);
        }

        // pull out the checkDigit
        $checkDigit = array_pop($characters);

        // put the string back together
        $number = implode('', $characters);
        $expectedCheckDigit = $this->getCheckDigit($number);

        return ($checkDigit === $expectedCheckDigit);
    }

    private function getCheckDigit($input)
    {
        // this method performs the luhn algorithm
        // to obtain a check digit

        $input = (string) $input;

        // first split up the string
        $numbers = str_split($input);

        // calculate the positional value.
        // when there is an even number of digits the second group will be multiplied, so p starts on 0
        // when there is an odd number of digits the first group will be multiplied, so p starts on 1
        $p = count($numbers) % 2;
        // run through each number
        foreach ($numbers as $i => $num) {
            $num = (int) $num;
            // every positional number needs to be multiplied by 2
            if ($p % 2) {
                $num = $num*2;
                // if the result was more than 9
                // add the individual digits
                $num = array_sum(str_split($num));
            }
            $numbers[$i] = $num;
            $p++;
        }

        // get the total value of all the digits
        $sum = array_sum($numbers);

        // get the remainder when dividing by 10
        $mod = $sum % 10;

        // subtract from 10
        $rem = 10 - $mod;

        // mod from 10 to catch if the result was 0
        $digit = $rem % 10;

        return $digit;
    }

}
