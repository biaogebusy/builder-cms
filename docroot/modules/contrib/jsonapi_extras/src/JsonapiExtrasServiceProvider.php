<?php

namespace Drupal\jsonapi_extras;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replace the resource type repository for our own configurable version.
 */
class JsonapiExtrasServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $settings = BootstrapConfigStorageFactory::get()
      ->read('jsonapi_extras.settings');

    if ($settings !== FALSE) {
      if ($container->getParameter('jsonapi.base_path') !== '/jsonapi') {
        $container->setParameter('jsonapi_extras.base_path_override_disabled', TRUE);
      }
      else {
        $container->setParameter('jsonapi.base_path', '/' . $settings['path_prefix']);
        $container->setParameter('jsonapi_extras.base_path_override_disabled', FALSE);
      }
    }

    // Enable normalizers in the "src-impostor-normalizers" directory to be
    // within the \Drupal\jsonapi\Normalizer namespace in order to circumvent
    // the encapsulation enforced by
    // \Drupal\jsonapi\Serializer\Serializer::__construct().
    $container_namespaces = $container->getParameter('container.namespaces');
    $container_modules = $container->getParameter('container.modules');
    $jsonapi_impostor_path = dirname($container_modules['jsonapi_extras']['pathname']) . '/src-impostor-normalizers';
    $container_namespaces['Drupal\jsonapi\Normalizer\ImpostorFrom\jsonapi_extras'][] = $jsonapi_impostor_path;
    // Manually include the impostor definitions to avoid class not found error
    // during compilation, which gets triggered though cache-clear.
    $container->getDefinition('serializer.normalizer.field_item.jsonapi_extras')
      ->setFile($jsonapi_impostor_path . '/FieldItemNormalizerImpostor.php');
    $container->getDefinition('serializer.normalizer.resource_identifier.jsonapi_extras')
      ->setFile($jsonapi_impostor_path . '/ResourceIdentifierNormalizerImpostor.php');
    $container->getDefinition('serializer.normalizer.resource_object.jsonapi_extras')
      ->setFile($jsonapi_impostor_path . '/ResourceObjectNormalizerImpostor.php');
    $container->getDefinition('serializer.normalizer.content_entity.jsonapi_extras')
      ->setFile($jsonapi_impostor_path . '/ContentEntityDenormalizerImpostor.php');
    $container->getDefinition('serializer.normalizer.config_entity.jsonapi_extras')
      ->setFile($jsonapi_impostor_path . '/ConfigEntityDenormalizerImpostor.php');
    $container->setParameter('container.namespaces', $container_namespaces);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter(('container.modules'));

    if (isset($modules['schemata_json_schema'])) {
      // Register field definition schema override.
      $container
        ->register('serializer.normalizer.field_definition.schema_json.jsonapi_extras', 'Drupal\jsonapi_extras\Normalizer\SchemaFieldDefinitionNormalizer')
        ->addTag('normalizer', ['priority' => 32])
        ->addArgument(new Reference('jsonapi.resource_type.repository'));

      // Register top-level schema override.
      $container
        ->register('serializer.normalizer.schemata_schema_normalizer.schema_json.jsonapi_extras', 'Drupal\jsonapi_extras\Normalizer\SchemataSchemaNormalizer')
        ->addTag('normalizer', ['priority' => 100])
        ->addArgument(new Reference('jsonapi.resource_type.repository'));
    }
  }

}
