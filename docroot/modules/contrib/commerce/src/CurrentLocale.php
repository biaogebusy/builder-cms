<?php

namespace Drupal\commerce;

use Drupal\commerce\Resolver\ChainLocaleResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Holds a reference to the current locale, resolved on demand.
 *
 * The ChainLocaleResolver runs the registered locale resolvers one by one until
 * one of them returns the locale.
 * The DefaultLocaleResolver runs last, and contains the default logic
 * which assembles the locale based on the current language and country.
 *
 * @see \Drupal\commerce\Resolver\ChainLocaleResolver
 * @see \Drupal\commerce\Resolver\DefaultLocaleResolver
 */
class CurrentLocale implements CurrentLocaleInterface {

  /**
   * Static cache of resolved locales. One per request.
   *
   * @var \SplObjectStorage
   */
  protected $locales;

  /**
   * Constructs a new CurrentLocale object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\commerce\Resolver\ChainLocaleResolverInterface $chainResolver
   *   The chain resolver.
   */
  public function __construct(protected RequestStack $requestStack, protected ChainLocaleResolverInterface $chainResolver) {
    $this->locales = new \SplObjectStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function getLocale() {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || !$this->locales->contains($request)) {
      $locale = $this->chainResolver->resolve();
      if (!$request) {
        return $locale;
      }
      $this->locales[$request] = $locale;
    }

    return $this->locales[$request];
  }

}
