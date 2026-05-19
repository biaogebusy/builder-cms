<?php

namespace Drupal\simple_oauth\Exception;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Exception thrown when the request is unauthorized.
 */
final class OAuthUnauthorizedHttpException extends UnauthorizedHttpException {}
