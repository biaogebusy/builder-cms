<?php

namespace Drupal\replication\Normalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\file\FileInterface;
use Drupal\multiversion\Entity\Index\MultiversionIndexFactory;
use Drupal\replication\ProcessFileAttachment;
use Drupal\replication\UsersMapping;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class FileEntityNormalizer extends ContentEntityNormalizer implements DenormalizerInterface {

  /**
   * @var string[]
   */
  protected $supportedInterfaceOrClass = ['Drupal\file\FileInterface'];

  /**
   * @var string[]
   */
  protected $format = ['json'];

  /**
   * @var \Drupal\replication\ProcessFileAttachment
   */
  protected $processFileAttachment;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   * @param \Drupal\multiversion\Entity\Index\MultiversionIndexFactory $index_factory
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   * @param \Drupal\replication\UsersMapping $users_mapping
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   * @param \Drupal\replication\ProcessFileAttachment $process_file_attachment
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, MultiversionIndexFactory $index_factory, LanguageManagerInterface $language_manager, UsersMapping $users_mapping, ModuleHandlerInterface $module_handler, SelectionPluginManagerInterface $selection_manager = NULL, EventDispatcherInterface $event_dispatcher = NULL, ProcessFileAttachment $process_file_attachment) {
    parent::__construct($entity_manager, $index_factory, $language_manager, $users_mapping, $module_handler, $selection_manager, $event_dispatcher);
    $this->processFileAttachment = $process_file_attachment;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    $normalized = parent::normalize($data, $format, $context);
    $file_system = \Drupal::service('file_system');
    $uri = $data->getFileUri();

    if (empty($uri)) {
      return $normalized;
    }

    if ($file_contents = @file_get_contents($uri)) {
      if (in_array($file_system->uriScheme($uri), ['public', 'private']) == FALSE) {
        $file_data = '';
      }
      else {
        $file_data = base64_encode($file_contents);
      }

      // @todo {@link https://www.drupal.org/node/2600360 Add revpos and other missing properties to the result array.}
      $normalized['@attachment'] = [
        'uuid' => $data->uuid(),
        'uri' => $uri,
        'content_type' => $data->getMimeType(),
        'digest' => 'md5-' . base64_encode(md5($file_contents)),
        'length' => $data->getSize(),
        'data' => $file_data,
      ];
    }
    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $file = NULL;
    if (!empty($data['@attachment']['uuid'])) {
      $workspace = isset($context['workspace']) ? $context['workspace'] : NULL;
      /** @var FileInterface $file */
      if ($file = $this->processFileAttachment->process($data['@attachment'], 'base64_stream', $workspace)) {
        // Update the data, because file name could change.
        $language_code = $file->language()->getId();
        $data[$language_code]['filename'][0]['value'] = $file->get('filename')->value;
        $data[$language_code]['uri'][0]['value'] = $file->get('uri')->value;
      }
      unset($data['@attachment']);
    }
    return parent::denormalize($data, $class, $format, $context);
  }

  public function supportsDenormalization($data, $type, $format = NULL) {
    // We need to accept both FileInterface and ContentEntityInterface classes.
    // File entities are treated as standard content entities.
    if (in_array($type, ['Drupal\Core\Entity\ContentEntityInterface', 'Drupal\file\FileInterface'], TRUE)) {
      // If a document has _attachment then we assume it's a file entity.
      if (!empty($data['@attachment'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
