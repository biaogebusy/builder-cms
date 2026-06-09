<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 禁止任何 HTTP 缓存层缓存 image_job 的 JSON:API 读响应。
 *
 * image_job 是随时变化的状态资源。读响应会从全局 page.max_age 继承
 * `Cache-Control: max-age=3600, public`,被浏览器/CDN 缓存住中间态(如 queued),
 * 实体转终态后这些缓存不认 Drupal cache tag,导致长时间看到旧状态;且 `public`
 * 用在权限受控的管理列表(view.image_jobs.page_admin)上还会把特权页面缓存后
 * 泄露给任何人。这里对相关读路径强制 no-store,与 SSE 控制器保持一致。
 */
final class ImageJobCacheSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // 低优先级,在 finish_response / page_cache 写头之后再覆盖。
    return [KernelEvents::RESPONSE => ['onResponse', -512]];
  }

  /**
   * 命中 image_job 的 JSON:API GET 读,或 image_jobs 视图页面/导出时强制不缓存。
   */
  public function onResponse(ResponseEvent $event): void {
    $request = $event->getRequest();
    if (!$request->isMethodCacheable()) {
      return;
    }
    $route = (string) $request->attributes->get('_route');
    if (str_starts_with($route, 'jsonapi.node--image_job.')
      || str_starts_with($route, 'view.image_jobs.')) {
      $event->getResponse()->headers->set('Cache-Control', 'no-store, private');
    }
  }

}
