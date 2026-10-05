<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\BundlePermissionHandlerTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\xinshi_analytics\Entity\AnalyticsDataset;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Dataset grants depend on their configuration and never grant entity access. */
final class DatasetPermissions implements ContainerInjectionInterface {

  use BundlePermissionHandlerTrait;
  use StringTranslationTrait;

  /** Constructs the permission provider with the configured entity storage. */
  public function __construct(private readonly EntityTypeManagerInterface $entityTypes) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container) {
    return new self($container->get('entity_type.manager'));
  }

  /** Generates permissions for both active and disabled datasets. */
  public function permissions(): array {
    return $this->generatePermissions($this->entityTypes->getStorage('analytics_dataset')->loadMultiple(),
      fn(AnalyticsDataset $dataset) => [$dataset->queryPermission() => [
        'title' => $this->t('Query analytics: %dataset', ['%dataset' => $dataset->label()]),
        'description' => $this->t('Count only entities and fields visible to the requesting account.'),
      ]]);
  }

}
