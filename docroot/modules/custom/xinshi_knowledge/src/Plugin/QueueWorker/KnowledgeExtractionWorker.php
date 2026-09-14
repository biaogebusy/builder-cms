<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\xinshi_knowledge\Service\AttachmentExtraction;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[QueueWorker(id: 'xinshi_knowledge_extract', title: new TranslatableMarkup('Knowledge document extraction'), cron: ['time' => 30])]
final class KnowledgeExtractionWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition,
    private readonly AttachmentExtraction $extraction) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('xinshi_knowledge.extraction'));
  }

  public function processItem($data): void {
    if (is_array($data)) {
      $this->extraction->process($data);
    }
  }

}
