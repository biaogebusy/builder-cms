<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInterface;
use Drupal\ai_provider_openai\Plugin\AiProvider\OpenAiProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\xinshi_ai\Service\SupplierConfiguration;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Shares the CMS supplier configuration with the Node chat service. */
#[AiProvider(
  id: 'xinshi',
  label: new TranslatableMarkup('信使网关 (New API)'),
)]
final class XinshiGatewayAiProvider extends OpenAiProvider implements ImageToImageInterface {

  use OpenAiCompatibleImageEditTrait {
    create as private createImageProvider;
  }

  protected SupplierConfiguration $suppliers;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = self::createImageProvider($container, $configuration, $plugin_id, $plugin_definition);
    $instance->suppliers = $container->get('xinshi_ai.supplier_configuration');
    return $instance;
  }

  /** Add image editing to the parent provider operations. */
  public function getSupportedOperationTypes(): array {
    return array_values(array_unique([...parent::getSupportedOperationTypes(), 'image_to_image']));
  }

  /** Use the same endpoint normalization as the server-only configuration response. */
  protected function loadClient(): void {
    $this->setEndpoint($this->suppliers->baseUrl('xinshi'));
    parent::loadClient();
  }

  /** Read the non-exportable key; never fall back to legacy configuration. */
  protected function loadApiKey(): string {
    try {
      return $this->suppliers->apiKey('xinshi');
    }
    catch (\RuntimeException) {
      throw new AiSetupFailureException('Supplier credentials are unavailable.');
    }
  }

}
