<?php

namespace Drupal\commerce\Resolver;

/**
 * Defines the interface for locale resolvers.
 *
 * Interface for services tagged as 'commerce.locale_resolver'.
 */
interface LocaleResolverInterface {

  /**
   * Resolves the locale.
   *
   * @return \Drupal\commerce\Locale|null
   *   The locale object, if resolved. Otherwise NULL, indicating that the next
   *   resolver in the chain should be called.
   */
  public function resolve();

}
