<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\EntityShareClient\Processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginBase;
use Drupal\entity_share_client\RuntimeImportContext;
use Drupal\file\FileInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handle physical file import.
 *
 * @ImportProcessor(
 *   id = "physical_file",
 *   label = @Translation("Physical file"),
 *   description = @Translation("When importing a File entity, also import the physical file."),
 *   stages = {
 *     "process_entity" = 0,
 *   },
 *   locked = false,
 * )
 */
class PhysicalFile extends ImportProcessorPluginBase implements PluginFormInterface {

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->streamWrapperManager = $container->get('stream_wrapper_manager');
    $instance->logger = $container->get('logger.channel.entity_share_client');
    $instance->remoteManager = $container->get('entity_share_client.remote_manager');
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'rename' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['rename'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rename imported files with the same name, instead of overwriting'),
      '#description' => $this->t('If a file with the same name exists, the imported file will be saved as filename_0 or filename_1... etc. <strong>Warning! This can make a lot of duplicated files on your websites!</strong>'),
      '#default_value' => $this->configuration['rename'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ErrorControlOperator)
   */
  public function processEntity(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity, array $entity_json_data) {
    if ($processed_entity instanceof FileInterface) {
      $field_mappings = $runtime_import_context->getFieldMappings();
      $entity_type_id = $processed_entity->getEntityTypeId();
      $entity_bundle = $processed_entity->bundle();

      $uri_public_name = FALSE;
      if (isset($field_mappings[$entity_type_id][$entity_bundle]['uri'])) {
        $uri_public_name = $field_mappings[$entity_type_id][$entity_bundle]['uri'];
      }

      if (!$uri_public_name || !isset($entity_json_data['attributes'][$uri_public_name])) {
        $this->logger->error('Impossible to get the URI of the file in JSON:API data. Please check that the server website is correctly exposing it.');
        $this->messenger()->addError($this->t('Impossible to get the URI of the file in JSON:API data. Please check that the server website is correctly exposing it.'));
        return;
      }

      $remote_file_uri = $entity_json_data['attributes'][$uri_public_name]['value'];
      $remote_file_url = $entity_json_data['attributes'][$uri_public_name]['url'];
      $stream_wrapper = $this->streamWrapperManager->getViaUri($remote_file_uri);
      $directory_uri = $stream_wrapper->dirname($remote_file_uri);
      $log_variables = [
        '%url' => $remote_file_url,
        '%directory' => $directory_uri,
        '%id' => $processed_entity->id(),
        '%uri' => $remote_file_uri,
      ];

      $file_overwrite_mode = $this->configuration['rename']
        ? FileSystemInterface::EXISTS_RENAME
        : FileSystemInterface::EXISTS_REPLACE;

      $file_destination = $this->fileSystem->getDestinationFilename($processed_entity->getFileUri(), $file_overwrite_mode);
      $processed_entity->setFileUri($file_destination);
      $processed_entity->setFilename($this->fileSystem->basename($file_destination));

      // Create the destination folder.
      if ($this->fileSystem->prepareDirectory($directory_uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        try {
          $response = $this->remoteManager->request($runtime_import_context->getRemote(), 'GET', $remote_file_url);
          $file_content = (string) $response->getBody();
          $result = @file_put_contents($file_destination, $file_content);
          if (!$result) {
            throw new \Exception('Error writing file to ' . $file_destination);
          }
        }
        catch (ClientException $e) {
          $this->logger->warning('Error importing file id %id. Missing file: %url', $log_variables);
          $this->messenger()->addWarning($this->t('Error importing file id %id. Missing file: %url', $log_variables));
        }
        catch (\Throwable $e) {
          $log_variables['@msg'] = $e->getMessage();
          $this->logger->error('Caught exception trying to import the file %url to %uri. Error message was @msg', $log_variables);
          $this->messenger()->addError($this->t('Caught exception trying to import the file %url to %uri', $log_variables));
        }
      }
      else {
        $this->logger->error('Impossible to write in the directory %directory', $log_variables);
        $this->messenger()->addError($this->t('Impossible to write in the directory %directory', $log_variables));
      }
    }
  }

}
