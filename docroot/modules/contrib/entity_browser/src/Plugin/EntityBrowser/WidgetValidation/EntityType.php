<?php

namespace Drupal\entity_browser\Plugin\EntityBrowser\WidgetValidation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_browser\Attribute\EntityBrowserWidgetValidation;
use Drupal\entity_browser\WidgetValidationBase;

/**
 * Validates that each passed Entity is of the correct type.
 */
#[EntityBrowserWidgetValidation(
  id: 'entity_type',
  label: new TranslatableMarkup('Entity type validator'),
  data_type: 'entity_reference',
  constraint: 'EntityType'
)]
class EntityType extends WidgetValidationBase {}
