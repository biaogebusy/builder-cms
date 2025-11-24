<?php

namespace Drupal\entity_print;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\entity_print\Event\FilenameAlterEvent;
use Drupal\entity_print\Event\PrintEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A service for generating filenames for printed documents.
 */
class FilenameGenerator implements FilenameGeneratorInterface {

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * FilenameGenerator constructor.
   *
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   Transliteration service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(TransliterationInterface $transliteration, EventDispatcherInterface $event_dispatcher) {
    $this->transliteration = $transliteration;
    $this->dispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function generateFilename(array $entities, ?callable $entity_label_callback = NULL) {
    $filenames = [];
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($entities as $entity) {
      if ($label = trim($this->sanitizeFilename($entity_label_callback ? $entity_label_callback($entity) : $entity->label(), $entity->language()->getId()))) {
        $filenames[] = $label;
      }
    }

    $event = $this->dispatcher->dispatch(new FilenameAlterEvent($filenames, $entities), PrintEvents::FILENAME_ALTER);
    $filenames = $event->getFilenames();

    return $filenames ? implode('-', $filenames) : static::DEFAULT_FILENAME;
  }

  /**
   * Gets a safe filename.
   *
   * @param string $filename
   *   The un-processed filename.
   * @param string $langcode
   *   The language of the filename.
   *
   * @return string
   *   The filename stripped to only safe characters.
   */
  protected function sanitizeFilename($filename, $langcode) {
    $transformed = $this->transliteration->transliterate((string) $filename, $langcode);
    return preg_replace("/[^A-Za-z0-9 ]/", '', $transformed);
  }

}
