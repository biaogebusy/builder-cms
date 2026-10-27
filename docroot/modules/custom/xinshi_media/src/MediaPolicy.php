<?php

declare(strict_types=1);

namespace Drupal\xinshi_media;

use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Separates library administration from existing published-page rendering. */
final class MediaPolicy {

  public function __construct(private readonly MediaTypes $types, private readonly RequestStack $requests) {}

  public function all(AccountInterface $account): bool {
    return $account->hasPermission('view all media assets') || $account->hasPermission('administer media');
  }

  public function inScope(MediaInterface $media, AccountInterface $account): bool {
    return $account->isAuthenticated() && ($this->all($account) ||
      (string) $media->getOwnerId() === (string) $account->id());
  }

  /** Private sources always retain owner scope, including normal entity readers. */
  public function privateSource(MediaInterface $media): bool {
    $type = $this->types->all()[$media->bundle()] ?? NULL;
    $file = $type ? $media->get($type['field'])->entity : NULL;
    return $file && str_starts_with($file->getFileUri(), 'private://');
  }

  /** Public-page rendering keeps core access; metadata APIs use the catalog boundary. */
  public function metadataRequest(): bool {
    $request = $this->requests->getCurrentRequest();
    if (!$request) return FALSE;
    $route = (string) $request->attributes->get('_route');
    $path = $request->getPathInfo();
    if ($route === 'xinshi_media.display') return FALSE;
    return str_starts_with($route, 'entity.media.') ||
      str_starts_with($route, 'jsonapi.media--') || str_starts_with($route, 'jsonapi.file--') ||
      str_starts_with($route, 'xinshi_media.') ||
      (bool) preg_match('@/(api/v[12]/(media|file)|api/v3/media|admin/content/media|media-library)(/|$)@', $path);
  }

}
