<?php

namespace Drupal\simple_oauth\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the Oauth2RedirectUri constraint.
 */
class Oauth2RedirectUriValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    foreach ($value->getValue() as $item) {
      if (!preg_match("
        /^
        [a-z][a-z0-9+\-\.]*:\/\/                                # Supporting various URL schemes
        (?:
          (?:[a-z0-9\-\.]|%[0-9a-f]{2})+                        # A domain name or a IPv4 address
          |(?:\[(?:[0-9a-f]{0,4}:)*(?:[0-9a-f]{0,4})\])         # or a well formed IPv6 address
        )
        (?::[0-9]+)?                                            # Server port number (optional)
        (?:[\/|\?]
          (?:[\w#!:\.\?\+=&@$'~*,;\/\(\)\[\]\-]|%[0-9a-f]{2})   # The path and query (optional)
        *)?
      $/xi", $item['value'])) {
        $this->context->addViolation($constraint->oauth2RedirectUriMessage, ['%url' => $item['value']]);
      }
    }
  }

}
