<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_billing\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xinshi_ai_billing\{AiBilling, BillingProfiles};
use Drupal\xinshi_ai_usage\Service\{ProducerIdentity, ServiceAuthException};
use Drupal\xinshi_credits\{CreditException, CreditValue, CreditWriteRequest};
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};

/** Separate user OAuth/session approval from signed executor evidence. */
final class BillingController implements ContainerInjectionInterface {

  public function __construct(
    private readonly AiBilling $billing,
    private readonly BillingProfiles $profiles,
    private readonly CreditWriteRequest $writes,
    private readonly ProducerIdentity $producers,
    private readonly FloodInterface $flood,
    private readonly AccountProxyInterface $user,
  ) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_ai_billing.billing'), $container->get('xinshi_ai_billing.profiles'),
      $container->get('xinshi_credits.write_request'), $container->get('xinshi_ai_usage.producer_identity'),
      $container->get('flood'), $container->get('current_user'));
  }

  /** Read-only pricing is private; quote persistence has a bounded admission rate. */
  public function quote(Request $request): JsonResponse {
    [$body, $key] = $this->writes->parse($request);
    $uid = (string) $this->user->id();
    if (!$this->flood->isAllowed('xinshi_ai_billing.quote', 60, 3600, $uid)) throw new CreditException('quote_rate_limited', 429);
    $this->flood->register('xinshi_ai_billing.quote', 3600, $uid);
    return new JsonResponse($this->billing->quote($body, $key));
  }

  /** Authorization uses the quote ID as its durable business key. */
  public function authorize(Request $request): JsonResponse {
    [$body] = $this->writes->parse($request);
    return new JsonResponse($this->billing->authorize($body));
  }

  /** Publish a new immutable version; activation remains an explicit deployment setting. */
  public function profile(Request $request): JsonResponse {
    [$body] = $this->writes->parse($request);
    $body = json_decode(json_encode($body, JSON_THROW_ON_ERROR), TRUE, 32, JSON_THROW_ON_ERROR);
    return new JsonResponse($this->profiles->publish($body));
  }

  /** Read one operation under the current scoped principal. */
  public function own(Request $request, string $operation): JsonResponse {
    CreditValue::fields($request->query->all(), [], ['noCache']);
    return new JsonResponse($this->billing->own($operation));
  }

  /** Admit a signed executor attempt only after user approval. */
  public function permit(Request $request): JsonResponse {
    [$producer, $body] = $this->signed($request);
    return new JsonResponse($this->billing->permit($producer, $body));
  }

  /** Repeated finalization reconciles evidence without dispatching models. */
  public function finalize(Request $request): JsonResponse {
    [$producer, $body] = $this->signed($request);
    return new JsonResponse($this->billing->finalize($producer, $body));
  }

  private function signed(Request $request): array {
    if ($request->getContentTypeFormat() !== 'json') throw new CreditException('invalid_content_type', 415);
    if (strlen($request->getContent()) > 32768) throw new CreditException('request_too_large', 413);
    CreditValue::fields($request->query->all(), []);
    try { $producer = $this->producers->authenticate($request); }
    catch (ServiceAuthException) { throw new CreditException('producer_denied', 403); }
    try {
      $object = json_decode($request->getContent(), FALSE, 16, JSON_THROW_ON_ERROR);
      if (!$object instanceof \stdClass) throw new CreditException('invalid_json');
      $body = json_decode($request->getContent(), TRUE, 16, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) { throw new CreditException('invalid_json'); }
    return [$producer, $body];
  }

}
