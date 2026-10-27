<?php

declare(strict_types=1);

namespace Drupal\xinshi_media\EventSubscriber;

use Drupal\file\Validation\FileValidationEvent;
use Drupal\xinshi_media\MediaException;
use Drupal\xinshi_media\MediaValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;

/** Sanitizes native and JSON:API uploads while their bytes are still in temporary storage. */
final class SvgUploadSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly MediaValidator $validator) {}

  public function validate(FileValidationEvent $event): void {
    $file = $event->file;
    if (strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)) !== 'svg' && $file->getMimeType() !== 'image/svg+xml') return;
    try {
      $size = filesize($file->getFileUri());
      if (!$size || $size > 1048576) throw new MediaException('invalid_svg', 422);
      $bytes = $this->validator->svg(file_get_contents($file->getFileUri()));
      if (file_put_contents($file->getFileUri(), $bytes) === FALSE) throw new MediaException('invalid_svg', 422);
      $file->setSize(strlen($bytes));
      $file->setMimeType('image/svg+xml');
    } catch (MediaException) {
      $event->violations->add(new ConstraintViolation('Invalid SVG file.', 'Invalid SVG file.', [], $file, '', NULL));
    }
  }

  public static function getSubscribedEvents(): array {
    return [FileValidationEvent::class => ['validate', 100]];
  }

}
