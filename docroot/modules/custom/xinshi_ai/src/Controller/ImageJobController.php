<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\xinshi_ai\AiTaskManager;
use Drupal\xinshi_ai\Exception\TaskNotFoundException;
use Drupal\xinshi_ai\Service\EventTicketServiceInterface;
use Drupal\xinshi_ai\Service\ImmediateJobRunner;
use Drupal\xinshi_ai\Service\JobLifecycleServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * 图片任务的创建与取消(/api/v3/image-jobs)。
 */
final class ImageJobController extends ControllerBase {

  private const QUEUE = 'xinshi_ai_image_job';
  private const TERMINAL = ['succeeded', 'failed', 'cancelled'];

  public function __construct(
    private readonly AiTaskManager $taskManager,
    private readonly JobLifecycleServiceInterface $lifecycle,
    private readonly EventTicketServiceInterface $eventTicket,
    private readonly QueueFactory $queueFactory,
    private readonly AccountInterface $account,
    private readonly ImmediateJobRunner $immediateRunner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.xinshi_ai_task'),
      $container->get('xinshi_ai.job_lifecycle'),
      $container->get('xinshi_ai.event_ticket'),
      $container->get('queue'),
      $container->get('current_user'),
      $container->get('xinshi_ai.immediate_job_runner'),
    );
  }

  /**
   * POST /api/v3/image-jobs。
   */
  public function submit(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
    }

    $input = [
      'jobKind' => $payload['jobKind'] ?? 'text_to_image',
      'platform' => $payload['platform'] ?? NULL,
      'model' => $payload['model'] ?? NULL,
      'prompt' => $payload['prompt'] ?? '',
      'negativePrompt' => $payload['negativePrompt'] ?? NULL,
      'params' => is_array($payload['params'] ?? NULL) ? $payload['params'] : [],
      'nRequested' => $payload['params']['n'] ?? $payload['n'] ?? NULL,
      'inputImage' => $payload['inputImage'] ?? NULL,
      'parentJob' => $payload['parentJob'] ?? NULL,
      'taskId' => $payload['taskId'] ?? NULL,
    ];

    try {
      $task = $this->taskManager->getByKind((string) $input['jobKind']);
    }
    catch (TaskNotFoundException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400);
    }

    $errors = $task->validate($input, $this->account);
    if ($errors) {
      return new JsonResponse(['errors' => $errors], 422);
    }

    $job = $this->lifecycle->createQueued($input, $this->account);
    $uuid = $job->uuid();
    $ticket = $this->eventTicket->issue($uuid, (int) $this->account->id());
    $this->queueFactory->get(self::QUEUE)->createItem([
      'jobUuid' => $uuid,
      'taskId' => $task->getId(),
    ]);

    // 默认(queue.worker=cron)在响应回客户端后即时消费队列,免去 cron 空窗;
    // 若配置为外部常驻消费者(如 advancedqueue)则不重复处理。
    $worker = (string) ($this->config('xinshi_ai.settings')->get('queue.worker') ?? 'cron');
    if ($worker === 'cron' || $worker === 'immediate') {
      $this->immediateRunner->schedule();
    }

    return new JsonResponse([
      'uuid' => $uuid,
      'status' => $job->get('field_status')->value,
      'nRequested' => (int) $job->get('field_n_requested')->value,
      'eventTicket' => $ticket,
    ], 202);
  }

  /**
   * POST /api/v3/image-jobs/{uuid}/cancel。
   */
  public function cancel(string $uuid): JsonResponse {
    $job = $this->lifecycle->loadJobByUuid($uuid);
    if (!$job) {
      return new JsonResponse(['error' => 'Job not found.'], 404);
    }
    if ((int) $job->getOwnerId() !== (int) $this->account->id()
      && !$this->account->hasPermission('administer xinshi_ai')) {
      return new JsonResponse(['error' => 'Forbidden.'], 403);
    }

    $status = $job->get('field_status')->value;
    if (in_array($status, self::TERMINAL, TRUE)) {
      return new JsonResponse(['uuid' => $uuid, 'status' => $status], 200);
    }

    $task = $this->taskManager->getByKind($job->get('field_job_kind')->value);
    $task->cancel($job);
    return new JsonResponse(['uuid' => $uuid, 'status' => 'cancelled'], 200);
  }

}
