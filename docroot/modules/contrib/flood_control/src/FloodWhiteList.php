<?php

namespace Drupal\flood_control;

use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Flood white list decorator.
 */
class FloodWhiteList implements FloodInterface {

  /**
   * The decorated flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs the FloodWhiteList.
   *
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(FloodInterface $flood, RequestStack $request_stack) {
    $this->flood = $flood;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed($name, $threshold, $window = 3600, $identifier = NULL) {
    if ($this->isIpWhitelisted()) {
      return TRUE;
    }

    return $this->flood->isAllowed($name, $threshold, $window, $identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function register($name, $window = 3600, $identifier = NULL) {
    return $this->flood->register($name, $window, $identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function clear($name, $identifier = NULL) {
    return $this->flood->clear($name, $identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    return $this->flood->garbageCollection();
  }

  /**
   * Checks if ip address is whitelisted.
   *
   * @param string $ipAddress
   *   Optional. IP address to be checked if it is in whitelist. If no ip value
   *   provided user's current ip will be used to be verified.
   *
   * @return bool
   *   TRUE if requested IP address is whitelisted, FALSE if it is not.
   */
  protected function isIpWhitelisted(string $ipAddress = ''): bool {
    $request = $this->requestStack->getCurrentRequest();

    if ($request && !$ipAddress) {
      $ipAddress = $request->getClientIp() ?? '';
    }

    // Gets the values from the config.
    $ipsWhiteList = flood_control_get_whitelist_ips();

    // Check if the current address is mentioned specifically.
    if (isset($ipsWhiteList['addresses']) && in_array($ipAddress, $ipsWhiteList['addresses'], TRUE)) {
      return TRUE;
    }

    // Check if any IP ranges are set, if so, continue, otherwise return false.
    if (empty($ipsWhiteList['ranges'])) {
      return FALSE;
    }

    // Check if the current IP address is within the ranges.
    foreach ($ipsWhiteList['ranges'] as $ipRange) {
      [$ipLower, $ipUpper] = explode('-', $ipRange, 2);
      $ipLowerDec = (float) sprintf("%u", ip2long($ipLower));
      $ipUpperDec = (float) sprintf("%u", ip2long($ipUpper));
      $ipAddressDec = (float) sprintf("%u", ip2long($ipAddress));

      if (($ipAddressDec >= $ipLowerDec) && ($ipAddressDec <= $ipUpperDec)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
