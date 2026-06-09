<?php

declare(strict_types=1);

namespace Drupal\ai\Entity;

/**
 * Enum for AI Guardrail Modes.
 */
enum AiGuardrailModeEnum: string {

  case PreGenerate = 'pre';
  case PostGenerate = 'post';
  case DuringGenerate = 'during';

}
