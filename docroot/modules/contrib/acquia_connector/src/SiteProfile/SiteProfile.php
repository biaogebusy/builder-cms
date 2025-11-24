<?php

namespace Drupal\acquia_connector\SiteProfile;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Site Profile methods.
 *
 * @package Drupal\acquia_connector
 */
class SiteProfile {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Acquia Connector Site Profile constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * Attempt to determine if this site is hosted with Acquia.
   *
   * @return bool
   *   TRUE if site is hosted with Acquia, otherwise FALSE.
   */
  public function checkAcquiaHosted() {
    return $this->requestStack->getCurrentRequest()->server->has('AH_SITE_ENVIRONMENT')
      && $this->requestStack->getCurrentRequest()->server->has('AH_SITE_NAME');
  }

  /**
   * Returns a unique string built on the current domain.
   *
   * @return string
   *   The Site ID when not hosted on Acquia.
   */
  private function getNonAcquiaSiteId() {
    $base_url = $this->requestStack->getCurrentRequest()->getHost();
    return $base_url . '_' . substr(md5(uniqid(mt_rand(), TRUE)), 0, 8);
  }

  /**
   * Generate the site name for connector.
   *
   * @param string $application_uuid
   *   The Application UUID.
   */
  public function getSiteName($application_uuid) {
    $prefix = substr($application_uuid, '-12');

    // Acquia Hosted.
    if ($this->checkAcquiaHosted()) {
      return $prefix . ': ' . $this->requestStack->getCurrentRequest()->server->get('AH_SITE_ENVIRONMENT');
    }
    // Locally Hosted.
    return $prefix . ': ' . $this->getNonAcquiaSiteId();

  }

  /**
   * Generate the machine name for connector.
   *
   * @param string $application_uuid
   *   The Application UUID.
   *
   * @return string
   *   The suggested Acquia Hosted machine name.
   */
  public function getMachineName($application_uuid): string {
    $prefix = substr($application_uuid, '-12');

    if ($this->checkAcquiaHosted()) {
      return $prefix . '__' . $this->requestStack->getCurrentRequest()->server->get('AH_SITE_ENVIRONMENT') . '__' . uniqid();
    }
    return $prefix . '__' . str_replace(['.', '-'], '_', $this->getNonAcquiaSiteId());
  }

}
