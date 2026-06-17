<?php

namespace Drupal\layout_builder_st\DependencyInjection;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\layout_builder\InlineBlockEntityOperations as CoreInlineBlockEntityOperations;
use Drupal\layout_builder_st\InlineBlockEntityOperations;

/**
 * Decorates the class resolver to load our InlineBlockEntityOperations class.
 */
final class ClassResolver implements ClassResolverInterface {

  public function __construct(
    private readonly ClassResolverInterface $decorated,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getInstanceFromDefinition($definition): object {
    if ($definition === CoreInlineBlockEntityOperations::class) {
      $definition = InlineBlockEntityOperations::class;
    }
    return $this->decorated->getInstanceFromDefinition($definition);
  }

}
