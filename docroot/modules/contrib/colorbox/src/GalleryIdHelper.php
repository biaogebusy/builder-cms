<?php

namespace Drupal\colorbox;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Utility\Token;

/**
 * Implementation of GalleryIdHelper.
 *
 * @package Drupal\colorbox
 */
class GalleryIdHelper {

  /**
   * The Gallery Token.
   *
   * @var null
   */
  protected $galleryToken = NULL;

  /**
   * The Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Token.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * GalleryIdHelper constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Configuration Factory.
   * @param \Drupal\Core\Utility\Token $token
   *   The Token.
   */
  public function __construct(ConfigFactoryInterface $configFactory, Token $token) {
    $this->configFactory = $configFactory;
    $this->token = $token;
  }

  /**
   * Generate ID.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Entity.
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The Item.
   * @param array $settings
   *   The Settings.
   *
   * @return string
   *   Return string.
   */
  public function generateId(ContentEntityInterface $entity, FieldItemInterface $item, array $settings) {
    $entity_bundle = $entity->bundle();
    $entity_type = $entity->getEntityTypeId();
    $config = $this->configFactory->get('colorbox.settings');

    // Build the gallery id.
    $id = $entity->id();
    $entity_id = !empty($id) ? $entity_bundle . '-' . $id : 'entity-id';
    $field_name = $item->getParent()->getName();

    switch ($settings['colorbox_gallery']) {
      case 'post':
        $gallery_id = 'gallery-' . $entity_id;
        break;

      case 'page':
        $gallery_id = 'gallery-all';
        break;

      case 'field_post':
        $gallery_id = 'gallery-' . $entity_id . '-' . $field_name;
        break;

      case 'field_page':
        $gallery_id = 'gallery-' . $field_name;
        break;

      case 'custom':
        $gallery_id = $this->token->replace(
          $settings['colorbox_gallery_custom'],
          [$entity_type => $entity, 'file' => $item],
          ['clear' => TRUE]
        );
        break;

      default:
        $gallery_id = '';
    }

    // If gallery id is not empty add unique per-request token to avoid.
    // images being added manually to galleries.
    if (!empty($gallery_id) && $config->get('advanced.unique_token')) {
      // Check if gallery token has already been set, we need to reuse.
      // the token for the whole request.
      if (is_null($this->galleryToken)) {
        // We use a short token since randomness is not critical.
        $this->galleryToken = Crypt::randomBytesBase64(8);
      }
      $gallery_id = $gallery_id . '-' . $this->galleryToken;
    }
    return $gallery_id;
  }

}
