<?php

namespace Drupal\commerce_order\Exception;

/**
 * Thrown when attempting to save an order that is locked for updating.
 */
class OrderLockedSaveException extends \RuntimeException {}
