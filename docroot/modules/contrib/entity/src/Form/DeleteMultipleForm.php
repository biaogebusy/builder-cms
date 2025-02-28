<?php

namespace Drupal\entity\Form;

use Drupal\Core\Entity\Form\DeleteMultipleForm as CoreDeleteMultipleForm;

@trigger_error('\Drupal\entity\Form\DeleteMultipleForm has been deprecated in favor of \Drupal\Core\Entity\Form\DeleteMultipleForm. Use that instead.');

/**
 * Provides an entities deletion confirmation form.
 *
 * @deprecated Use \Drupal\Core\Entity\Form\DeleteMultipleForm instead.
 */
class DeleteMultipleForm extends CoreDeleteMultipleForm {}
