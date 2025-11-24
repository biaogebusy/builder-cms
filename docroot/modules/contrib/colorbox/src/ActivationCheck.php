<?php

namespace Drupal\colorbox;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Implementation of ActivationCheckInterface.
 */
class ActivationCheck implements ActivationCheckInterface {

  /**
   * The colorbox settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $settings;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Create an instance of ActivationCheck.
   */
  public function __construct(ConfigFactoryInterface $config, RequestStack $request) {
    $this->settings = $config->get('colorbox.settings');
    $this->request = $request->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    return $this->request->get('colorbox') !== 'no';
  }

}
