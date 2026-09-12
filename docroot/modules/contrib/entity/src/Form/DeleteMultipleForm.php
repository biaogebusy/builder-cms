<?php

namespace Drupal\entity\Form;

use Drupal\Core\Entity\Form\DeleteMultipleForm as CoreDeleteMultipleForm;

@trigger_error('\Drupal\entity\Form\DeleteMultipleForm has been deprecated in favor of \Drupal\Core\Entity\Form\DeleteMultipleForm. Use that instead.');

/**
 * Provides an entities deletion confirmation form.
 *
 * @deprecated in entity:8.x-1.7 and is removed from entity:2.0.0. Use \Drupal\Core\Entity\Form\DeleteMultipleForm instead.
 * @see https://www.drupal.org/node/2952495
 */
class DeleteMultipleForm extends CoreDeleteMultipleForm {}
