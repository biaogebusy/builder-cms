<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\xinshi_ai\AiTaskManager;
use Drupal\xinshi_ai\Exception\TaskNotFoundException;
use Drupal\xinshi_ai\Exception\InputImageAccessException;
use Drupal\xinshi_ai\Exception\ImageSafetyException;
use Drupal\xinshi_ai\Service\PublicUrlPolicy;
use Drupal\xinshi_ai\Service\EventTicketServiceInterface;
use Drupal\xinshi_ai\Service\ImageJobCredentialVault;
use Drupal\xinshi_ai\Service\ImmediateJobRunner;
use Drupal\xinshi_ai\Service\JobLifecycleServiceInterface;
use Drupal\xinshi_ai_billing\ImageBilling;
use Drupal\xinshi_credits\CreditWriteRequest;
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
    private readonly ImageJobCredentialVault $credentials,
    private readonly PublicUrlPolicy $urlPolicy,
    private readonly ?ImageBilling $billing = NULL,
    private readonly ?CreditWriteRequest $creditWrites = NULL,
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
      $container->get('xinshi_ai.image_job_credentials'),
      $container->get('xinshi_ai.public_url_policy'),
      $container->has('xinshi_ai_billing.images') ? $container->get('xinshi_ai_billing.images') : NULL,
      $container->has('xinshi_credits.write_request') ? $container->get('xinshi_credits.write_request') : NULL,
    );
  }

  /**
   * POST /api/v3/image-jobs。
   *
   * custom 平台的连接凭据放在顶层 `credentials: { endpoint, apiKey }`(UB2.7);
   * 它们只进加密 vault,不进任务实体、事件或日志。旧客户端放在 params 里的
   * endpoint / api_key 同样被剥离后入 vault。
   */
  public function submit(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
    }

    $params = is_array($payload['params'] ?? NULL) ? $payload['params'] : [];
    $platform = (string) ($payload['platform'] ?? '');
    $credentials = ImageJobCredentialVault::fromPayload($payload, $params);
    $input = self::input($payload, $params);

    try {
      $task = $this->taskManager->getByKind((string) $input['jobKind']);
    }
    catch (TaskNotFoundException $e) {
      return new JsonResponse(['error' => $e->getMessage()], 400);
    }

    $errors = $task->validate($input, $this->account);
    $credentialError = ImageJobCredentialVault::validate($credentials, $platform);
    if ($credentialError !== NULL) {
      $errors['credentials'] = $credentialError;
    }
    elseif ($platform === 'custom') {
      try {
        $this->urlPolicy->destination($credentials['endpoint']);
      }
      catch (ImageSafetyException $e) {
        $errors['credentials'] = $e->getMessage();
      }
    }
    if ($errors) {
      return new JsonResponse(['errors' => $errors], 422);
    }

    try {
      if (isset($payload['billingQuoteId'])) {
        if (!$this->billing || !$this->creditWrites) return new JsonResponse(['code' => 'billing_unavailable'], 503);
        $this->creditWrites->parse($request);
      }
      $create = fn(?string $operation = NULL) => $this->lifecycle->createQueued($input, $this->account, $operation);
      $job = $this->billing ? $this->billing->create($input, $payload['billingQuoteId'] ?? NULL, $create) : $create();
    }
    catch (InputImageAccessException $e) {
      return new JsonResponse(['error' => $e->getMessage(), 'code' => 'forbidden'], 403);
    }
    catch (\Drupal\xinshi_credits\CreditException $e) {
      return new JsonResponse(['code' => $e->reason], $e->status);
    }
    $uuid = $job->uuid();
    if ($platform === 'custom') {
      try {
        $this->credentials->store($uuid, (string) $credentials['endpoint'], (string) $credentials['api_key']);
      }
      catch (\Throwable $e) {
        // Without its key the job could never run; do not leave it queued.
        $job->delete();
        $this->getLogger('xinshi_ai')->error('Image job credentials could not be stored: @message', [
          '@message' => $e->getMessage(),
        ]);
        return new JsonResponse(['error' => 'Credentials could not be stored; try again later.'], 503);
      }
    }
    $ticket = $this->eventTicket->issue($uuid, (int) $this->account->id());
    $this->queueFactory->get(self::QUEUE)->createItem([
      'jobUuid' => $uuid,
      'taskId' => $task->getId(),
    ]);

    // Consume after the response unless an external worker owns the queue.
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

  /** Quote without creating a job; disabled installations retain their original behavior. */
  public function quote(Request $request): JsonResponse {
    $headers = ['Cache-Control' => 'private, no-store'];
    if (!$this->billing) return new JsonResponse(['status' => 'disabled'], 200, $headers);
    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload) || array_is_list($payload) || strlen($request->getContent()) > 100000) {
      return new JsonResponse(['code' => 'invalid_image_request'], 422, $headers);
    }
    $params = is_array($payload['params'] ?? NULL) ? $payload['params'] : [];
    ImageJobCredentialVault::fromPayload($payload, $params);
    try {
      if ($status = $this->billing->exemption((string) ($payload['platform'] ?? ''))) {
        return new JsonResponse(['status' => $status], 200, $headers);
      }
      $this->creditWrites?->parse($request);
      $result = $this->billing->quote(self::input($payload, $params), (string) $request->headers->get('Idempotency-Key'));
      return new JsonResponse($result, 200, $headers);
    }
    catch (\Drupal\xinshi_credits\CreditException $e) {
      return new JsonResponse(['code' => $e->reason], $e->status, $headers);
    }
  }

  /** Share the exact request projection between quotation and task creation. */
  private static function input(array $payload, array $params): array {
    return [
      'jobKind' => $payload['jobKind'] ?? 'text_to_image',
      'platform' => $payload['platform'] ?? NULL,
      'model' => $payload['model'] ?? NULL,
      'prompt' => $payload['prompt'] ?? '',
      'negativePrompt' => $payload['negativePrompt'] ?? NULL,
      'params' => $params,
      'nRequested' => $payload['params']['n'] ?? $payload['n'] ?? NULL,
      'inputImage' => $payload['inputImage'] ?? NULL,
      'parentJob' => $payload['parentJob'] ?? NULL,
      'taskId' => $payload['taskId'] ?? NULL,
    ];
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
    // The key is not needed once the job ended; a worker still running the
    // call already loaded it.
    if (in_array($job->get('field_status')->value, self::TERMINAL, TRUE)) {
      $this->credentials->delete($uuid);
    }
    // A job that finished while the cancel was on its way keeps its outcome;
    // the response reports the stored status rather than a cancel that did
    // not take effect.
    return new JsonResponse(['uuid' => $uuid, 'status' => $job->get('field_status')->value], 200);
  }

}
