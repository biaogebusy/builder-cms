<?php

namespace Drupal\commerce;

use Drupal\commerce\Resolver\ChainCountryResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Holds a reference to the current country, resolved on demand.
 *
 * The ChainCountryResolver runs the registered country resolvers one by one
 * until one of them returns the country.
 * The DefaultCountryResolver runs last, and will select the site's default
 * country. Custom resolvers can choose based on the user profile, GeoIP, etc.
 *
 * @see \Drupal\commerce\Resolver\ChainCountryResolver
 * @see \Drupal\commerce\Resolver\DefaultCountryResolver
 */
class CurrentCountry implements CurrentCountryInterface {

  /**
   * Static cache of resolved countries. One per request.
   *
   * @var \SplObjectStorage
   */
  protected $countries;

  /**
   * Constructs a new CurrentCountry object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\commerce\Resolver\ChainCountryResolverInterface $chainResolver
   *   The chain resolver.
   */
  public function __construct(protected RequestStack $requestStack, protected ChainCountryResolverInterface $chainResolver) {
    $this->countries = new \SplObjectStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function getCountry() {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || !$this->countries->contains($request)) {
      $country = $this->chainResolver->resolve();
      if (!$request) {
        return $country;
      }
      $this->countries[$request] = $country;
    }

    return $this->countries[$request];
  }

}
