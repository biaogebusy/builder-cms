<?php

namespace Drupal\media_entity_instagram\Plugin\media\Source;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\media\Plugin\media\Source\OEmbed;

/**
 * Implementation of an oEmbed Instagram source.
 *
 * @MediaSource(
 *   id = "oembed:instagram",
 *   label = @Translation("Instagram"),
 *   description = @Translation("Use Facebooks graph API for reusable instagrams."),
 *   allowed_field_types = {"string", "link"},
 *   default_thumbnail_filename = "instagram.png",
 *   providers = {"Instagram"},
 *   forms = {"media_library_add" = "\Drupal\media_entity_instagram\Form\InstagramMediaLibraryAddForm"}
 * )
 */
class Instagram extends OEmbed {

  /**
   * List of validation regular expressions.
   *
   * @var array
   */
  public static $validationRegexp = [
    '@((http|https):){0,1}//(www\.){0,1}instagram\.com/p/(?<shortcode>[a-z0-9_-]+)@i' => 'shortcode',
    '@((http|https):){0,1}//(www\.){0,1}instagr\.am/p/(?<shortcode>[a-z0-9_-]+)@i' => 'shortcode',
    '@((http|https):){0,1}//(www\.){0,1}instagram\.com/tv/(?<shortcode>[a-z0-9_-]+)@i' => 'shortcode',
    '@((http|https):){0,1}//(www\.){0,1}instagr\.am/tv/(?<shortcode>[a-z0-9_-]+)@i' => 'shortcode',
  ];

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      'shortcode' => $this->t('Instagram shortcode'),
      'type' => $this->t('Resource type'),
      'author_name' => $this->t('The name of the author/owner'),
      'default_name' => $this->t('Default name of the media item'),
      'provider_name' => $this->t("The name of the provider"),
      'provider_url' => $this->t('The URL of the provider'),
      'thumbnail_uri' => $this->t('Local URI of the thumbnail'),
      'thumbnail_width' => $this->t('Thumbnail width'),
      'thumbnail_height' => $this->t('Thumbnail height'),
      'width' => $this->t('The width of the resource'),
      'html' => $this->t('The HTML representation of the resource'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    switch ($name) {
      case 'default_name':
        // Try to get some fields that need the API, if not available, just use
        // the shortcode as default name.
        $username = $this->getMetadata($media, 'author_name');
        $shortcode = $this->getMetadata($media, 'shortcode');
        if ($username && $shortcode) {
          return $username . ' - ' . $shortcode;
        }
        else {
          if (!empty($shortcode)) {
            return $shortcode;
          }
        }
        // Fallback to the parent's default name if everything else failed.
        return parent::getMetadata($media, 'default_name');

      case 'shortcode':
        $matches = $this->matchRegexp($media);
        if (is_array($matches) && !empty($matches['shortcode'])) {
          return $matches['shortcode'];
        }
        return FALSE;
    }

    return parent::getMetadata($media, $name);
  }

  /**
   * Runs preg_match on embed code/URL.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media object.
   *
   * @return array|bool
   *   Array of preg matches or FALSE if no match.
   *
   * @see preg_match()
   */
  protected function matchRegexp(MediaInterface $media) {
    $matches = [];

    if (isset($this->configuration['source_field'])) {
      $source_field = $this->configuration['source_field'];
      if ($media->hasField($source_field) && !$media->get($source_field)->isEmpty()) {
        $property_name = $media->{$source_field}->first()->mainPropertyName();
        foreach (static::$validationRegexp as $pattern => $key) {
          if (preg_match($pattern, $media->{$source_field}->{$property_name}, $matches)) {
            return $matches;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareViewDisplay(MediaTypeInterface $type, EntityViewDisplayInterface $display) {
    $display->setComponent($this->getSourceFieldDefinition($type)->getName(), [
      'type' => 'instagram_embed',
      'label' => 'visually_hidden',
    ]);
  }

}
