<?php

namespace Drupal\ai\Enum;

/**
 * Enum of Vector DB capabilities, which aren't shared across all of these.
 */
enum VdbCapability: string {
  case GroupBy = 'Grouping matches to avoid duplicities';
}
