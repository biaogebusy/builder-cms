<?php

namespace Drupal\ctools\Event;

use Drupal\ctools\Wizard\FormWizardInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * An event for altering form wizard values.
 */
class WizardEvent extends Event {

  /**
   * The wizard object.
   *
   * @var \Drupal\ctools\Wizard\FormWizardInterface
   */
  protected $wizard;

  /**
   * The wizard values.
   *
   * @var mixed
   */
  protected $values;

  /**
   * Constructs a new WizardEvent.
   *
   * @param \Drupal\ctools\Wizard\FormWizardInterface $wizard
   *   The wizard object.
   * @param mixed $values
   *   The wizard values.
   */
  public function __construct(FormWizardInterface $wizard, $values) {
    $this->wizard = $wizard;
    $this->values = $values;
  }

  /**
   * Gets the wizard.
   *
   * @return \Drupal\ctools\Wizard\FormWizardInterface
   *   The wizard object.
   */
  public function getWizard() {
    return $this->wizard;
  }

  /**
   * Gets the wizard values.
   *
   * @return mixed
   *   The wizard values.
   */
  public function getValues() {
    return $this->values;
  }

  /**
   * Sets the wizard values.
   *
   * @param mixed $values
   *   The wizard values.
   *
   * @return $this
   */
  public function setValues($values) {
    $this->values = $values;
    return $this;
  }

}
