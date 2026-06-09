<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\xinshi_ai\Service\EventStreamServiceInterface;
use Drupal\xinshi_ai\Service\JobLifecycleServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * image_job 进度的 SSE 推送(/api/v3/image-jobs/{uuid}/events)。
 *
 * 鉴权用 ticket(创建任务时签发),不走用户 OAuth。
 */
final class ImageJobEventsController extends ControllerBase {

  private const DEFAULT_HEARTBEAT = 25;
  private const DEFAULT_MAX_LIFETIME = 1200;

  public function __construct(
    private readonly EventStreamServiceInterface $eventStream,
    private readonly JobLifecycleServiceInterface $lifecycle,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('xinshi_ai.event_stream'),
      $container->get('xinshi_ai.job_lifecycle'),
    );
  }

  /**
   * GET /api/v3/image-jobs/{uuid}/events。
   */
  public function stream(string $uuid, Request $request): StreamedResponse {
    $cursor = (int) $request->query->get('cursor', 0);
    $sse = $this->config('xinshi_ai.settings')->get('sse') ?? [];
    $heartbeat = max(1, (int) ($sse['heartbeat_seconds'] ?? self::DEFAULT_HEARTBEAT));
    $maxLifetime = max(1, (int) ($sse['max_lifetime_seconds'] ?? self::DEFAULT_MAX_LIFETIME));

    $response = new StreamedResponse(function () use ($uuid, $cursor, $heartbeat, $maxLifetime) {
      @set_time_limit(0);
      ignore_user_abort(FALSE);
      $started = time();

      while (!connection_aborted()) {
        // read() 阻塞至多 $heartbeat 秒;无新事件即返回空,确保每轮都能发心跳。
        foreach ($this->eventStream->read($uuid, $cursor, $heartbeat) as $e) {
          echo "event: {$e['event']}\n";
          echo "id: {$e['seq']}\n";
          echo 'data: ' . json_encode($e['data'], JSON_UNESCAPED_UNICODE) . "\n\n";
          $cursor = max($cursor, $e['seq']);
        }
        // 心跳注释帧:即便本轮无事件也写字节,避免 nginx/CDN 因空闲(默认 60s)掐断连接。
        echo ": ping\n\n";
        @ob_flush();
        @flush();

        if ($this->lifecycle->isTerminal($uuid)) {
          break;
        }
        if (time() - $started > $maxLifetime) {
          break;
        }
      }
    });

    $response->headers->set('Content-Type', 'text/event-stream');
    $response->headers->set('Cache-Control', 'no-cache, no-store');
    $response->headers->set('X-Accel-Buffering', 'no');
    $response->headers->set('Connection', 'keep-alive');
    return $response;
  }

  /**
   * ticket 鉴权(_custom_access)。
   */
  public static function accessByTicket(string $uuid, Request $request, AccountInterface $account): AccessResultInterface {
    $ticket = $request->query->get('ticket');
    if (!$ticket) {
      return AccessResult::forbidden('Missing ticket')->setCacheMaxAge(0);
    }
    $ok = \Drupal::service('xinshi_ai.event_ticket')->verify((string) $ticket, $uuid);
    return $ok
      ? AccessResult::allowed()->setCacheMaxAge(0)
      : AccessResult::forbidden('Invalid ticket')->setCacheMaxAge(0);
  }

}
