<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\xinshi_knowledge\Service\DocumentRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[SearchApiProcessor(
  id: 'xinshi_knowledge_text',
  label: new TranslatableMarkup('Knowledge document text'),
  description: new TranslatableMarkup('Ready source text and its version, including private document attachments.'),
  stages: ['add_properties' => 0, 'alter_items' => 0],
)]
final class KnowledgeText extends ProcessorPluginBase {

  private DocumentRepository $documents;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->documents = $container->get('xinshi_knowledge.documents');
    return $instance;
  }

  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    if ($datasource) {
      return [];
    }
    $properties = [];
    foreach (['xinshi_document_text' => 'Knowledge text', 'xinshi_document_snapshot' => 'Source snapshot'] as $name => $label) {
      $properties[$name] = new ProcessorProperty([
        'label' => $this->t($label), 'type' => 'string', 'processor_id' => $this->getPluginId(),
      ]);
    }
    return $properties;
  }

  public function alterIndexedItems(array &$items): void {
    foreach ($items as $id => $item) {
      $node = $item->getOriginalObject()->getValue();
      try {
        if (!$node instanceof NodeInterface) {
          unset($items[$id]);
          continue;
        }
        $source = $this->documents->content($node);
        $item->setExtraData('xinshi_knowledge_source', $source);
      }
      catch (\DomainException) {
        unset($items[$id]);
      }
    }
  }

  public function addFieldValues(ItemInterface $item): void {
    $source = $item->getExtraData('xinshi_knowledge_source');
    if (!$source) {
      return;
    }
    foreach (['xinshi_document_text' => 'content', 'xinshi_document_snapshot' => 'snapshot'] as $path => $key) {
      foreach ($this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, $path) as $field) {
        $field->addValue($source[$key]);
      }
    }
  }

}
