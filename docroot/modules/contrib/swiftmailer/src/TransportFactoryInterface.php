<?php

namespace Drupal\swiftmailer;

/**
 * An interface for transport factory services.
 */
interface TransportFactoryInterface {

  /**
   * Returns the transport type configured as default.
   *
   * @return string
   *   The configured transport method.
   */
  public function getDefaultTransportMethod();

  /**
   * Instantiates a transport object of the specified type.
   *
   * @param string $transport_type
   *   The type of transform to instantiate.
   *
   * @return \Swift_Transport
   *   A new instance of the specified transport type.
   */
  public function getTransport($transport_type);

}
