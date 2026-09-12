<?php

declare(strict_types=1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_share_client\Attribute\ImportProcessor;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Drupal\redirect\Entity\Redirect;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters out redirects if the redirect hash matches a local one.
 *
 * This can happen if the server and client sites have redirects which were
 * created independently, and so have different UUIDs.
 */
#[ImportProcessor(
  id: 'redirect_hash_filter',
  label: new TranslatableMarkup('Redirect hash filter'),
  description: new TranslatableMarkup('Filters out redirects whose source matches an existing redirect but whose UUIDs are different.'),
  stages: [
    'is_entity_importable' => -10,
  ],
)]
class RedirectHashFilter extends ImportProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.channel.entity_share_client'),
    );
  }

  /**
   * Creates a RedirectHashFilter instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function isEntityImportable(RuntimeImportContext $runtime_import_context, array $entity_json_data) {
    [$entity_type_id, ] = explode('--', $entity_json_data['type']);

    // We only say something about redirect entities.
    if ($entity_type_id != 'redirect') {
      return TRUE;
    }

    $redirect_hash = Redirect::generateHash(
      $entity_json_data['attributes']['redirect_source']['path'],
      $entity_json_data['attributes']['redirect_source']['query'],
      $entity_json_data['attributes']['language'],
    );

    $redirect_storage = $this->entityTypeManager->getStorage('redirect');
    $existing_redirect = $redirect_storage->loadByProperties(['hash' => $redirect_hash]);

    if (empty($existing_redirect)) {
      return TRUE;
    }
    else {
      $this->logger->warning('Redirect with uuid @uuid was skipped because a redirect with the same redirect hash @hash already exists.', [
        '@uuid' => $entity_json_data['id'],
        '@hash' => $redirect_hash,
      ]);

      return FALSE;
    }
  }

}
