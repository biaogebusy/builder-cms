<?php

declare(strict_types = 1);

namespace Drupal\sms\Exception;

/**
 * Thrown if no gateway could be determined for a recipient.
 */
class RecipientRouteException extends SmsException {}
