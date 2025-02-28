<?php

namespace Drupal\replication\Normalizer;

use Drupal\menu_link_content\MenuLinkContentInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class MenuLinkContentNormalizer extends ContentEntityNormalizer implements DenormalizerInterface {

  /**
   * @var string[]
   */
  protected $supportedInterfaceOrClass = ['Drupal\menu_link_content\MenuLinkContentInterface'];

  /**
   * @var string[]
   */
  protected $format = ['json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($data, $format = NULL, array $context = []) {
    $normalized = parent::normalize($data, $format, $context);

    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $denormalized = parent::denormalize($data, $class, $format, $context);

    $entity_type_id = 'menu_link_content';
    $parent = ($denormalized instanceof MenuLinkContentInterface) ? $denormalized->getParentId() : NULL;
    if (!empty($parent) && strpos($parent, $entity_type_id) === 0) {
      list($type, $uuid,) = explode(':', $parent);
      if ($type === 'menu_link_content' && $uuid) {
        $storage = $this->entityTypeManager->getStorage($entity_type_id);
        if (!empty($context['workspace'])) {
          $storage->useWorkspace($context['workspace']->id());
        }
        $parent = $storage->loadByProperties(['uuid' => $uuid]);
        $parent = reset($parent);
        if ($parent instanceof MenuLinkContentInterface && $parent->id()) {
          $denormalized->parent->value = $type . ':' . $uuid . ':' . $parent->id();
        }
        elseif (!$parent) {
          // Create a new menu link as stub.
          $selection_instance = $this->selectionManager->getInstance(['target_type' => $entity_type_id]);
          $parent = $selection_instance->createNewEntity($entity_type_id, $entity_type_id, rand(), 1);
          // Indicate that this revision is a stub.
          $parent->_rev->is_stub = TRUE;
          $parent->uuid->value = $uuid;
          $parent->langcode->value = $data['@context']['@language'];
          $parent->link->uri = 'internal:/';
          if (!empty($context['workspace'])) {
            $parent->workspace->entity = $context['workspace'];
          }
          $parent->menu_name->value = $denormalized->getMenuName();
          $parent->save();
          $denormalized->parent->value = $type . ':' . $uuid . ':' . $parent->id();
        }
        $storage->useWorkspace(NULL);
      }
    }

    return $denormalized;
  }

  public function supportsDenormalization($data, $type, $format = NULL) {
    if (in_array($type, ['Drupal\menu_link_content\MenuLinkContentInterface', 'Drupal\Core\Entity\ContentEntityInterface'], true)) {
      if (isset($data['@type']) && $data['@type'] == 'menu_link_content') {
        return TRUE;
      }
    }
    return FALSE;
  }

}
