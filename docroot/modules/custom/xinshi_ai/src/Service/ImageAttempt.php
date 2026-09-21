<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\xinshi_ai_usage\Contract\ImageUsageNormalizer;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Psr\Log\LoggerInterface;

/**
 * The usage attempt of one provider call inside an image job (UB2.4).
 *
 * Observations never replace the provider result: a bookkeeping failure is
 * logged, the job carries on. Delivery facts are appended per artifact, so a
 * replayed queue item cannot create a second chargeable delivery.
 */
final class ImageAttempt {

  public const ARTIFACT_MEDIA = 'media';
  public const ARTIFACT_ASSET = 'image_asset';
  private const REQUEST_ID_MAX_LENGTH = 191;

  private bool $observed = FALSE;

  public function __construct(
    private readonly LocalUsageProducer $producer,
    private readonly LoggerInterface $logger,
    public readonly string $siteId,
    public readonly string $operationId,
    public readonly string $attemptId,
    public readonly int $attemptNo,
  ) {}

  public function isObserved(): bool {
    return $this->observed;
  }

  /**
   * The provider answered; usage is taken from the raw body before any decoding.
   */
  public function succeeded(mixed $raw, ProviderRequestTrace $trace): void {
    $this->observe([
      'outcome' => 'succeeded',
      'dispatch_state' => 'sent',
      'usage' => ImageUsageNormalizer::normalize($raw),
      'error_code' => NULL,
      'gateway_request_id' => self::requestId($trace),
    ]);
  }

  /**
   * The call failed; the transport trace decides whether it was ever sent.
   */
  public function failed(string $errorCode, ProviderRequestTrace $trace): void {
    $dispatch = $trace->dispatchState();
    $this->observe([
      'outcome' => $dispatch === 'not_sent' ? 'not_sent' : 'failed',
      'dispatch_state' => $dispatch,
      'usage' => NULL,
      'error_code' => $errorCode,
      'gateway_request_id' => self::requestId($trace),
    ]);
  }

  /** The image file and media entity exist; the user cannot see them yet. */
  public function persisted(int $outputIndex, string $mediaUuid): void {
    $this->deliver($outputIndex, self::ARTIFACT_MEDIA, $mediaUuid, LocalUsageProducer::STATE_PERSISTED);
  }

  /** The image asset the user can read exists: the chargeable delivery. */
  public function committed(int $outputIndex, string $assetUuid): void {
    $this->deliver($outputIndex, self::ARTIFACT_ASSET, $assetUuid, LocalUsageProducer::STATE_COMMITTED);
  }

  private function observe(array $observation): void {
    $this->observed = TRUE;
    try {
      $this->producer->observe($this->attemptId, $observation);
    }
    catch (\Throwable $e) {
      $this->logger->error('Usage observation of attempt @attempt lost: @message', [
        '@attempt' => $this->attemptId, '@message' => $e->getMessage(),
      ]);
    }
  }

  private function deliver(int $outputIndex, string $kind, string $ref, string $state): void {
    try {
      $this->producer->recordDelivery($this->siteId, $this->operationId, $this->attemptId, $outputIndex,
        $kind, $ref, NULL, $state);
    }
    catch (\Throwable $e) {
      $this->logger->error('Delivery fact of attempt @attempt #@index lost: @message', [
        '@attempt' => $this->attemptId, '@index' => $outputIndex, '@message' => $e->getMessage(),
      ]);
    }
  }

  private static function requestId(ProviderRequestTrace $trace): ?string {
    $id = $trace->requestId();
    if ($id === NULL || strlen($id) > self::REQUEST_ID_MAX_LENGTH || preg_match('/[^\x21-\x7e]/', $id)) {
      return NULL;
    }
    return $id;
  }

}
