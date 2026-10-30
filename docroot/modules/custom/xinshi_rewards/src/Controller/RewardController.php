<?php

declare(strict_types=1);

namespace Drupal\xinshi_rewards\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\xinshi_credits\CreditValue;
use Drupal\xinshi_credits\CreditWriteRequest;
use Drupal\xinshi_rewards\Rewards;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** HTTP transport for server-qualified rewards. */
final class RewardController implements ContainerInjectionInterface {

  public function __construct(private readonly Rewards $rewards, private readonly CreditWriteRequest $writes) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_rewards.rewards'), $container->get('xinshi_credits.write_request'));
  }

  /** Read current daily reward conditions. */
  public function status(Request $request): JsonResponse {
    CreditValue::fields($request->query->all(), []);
    return new JsonResponse($this->rewards->status());
  }

  /** Process a visit with identity and date determined by the server. */
  public function daily(Request $request): JsonResponse {
    [$body, $key] = $this->writes->parse($request);
    CreditValue::fields($body, []);
    return new JsonResponse($this->rewards->daily($key));
  }

  /** Process an authorized administrator gift. */
  public function gift(Request $request): JsonResponse {
    [$body, $key] = $this->writes->parse($request);
    return new JsonResponse($this->rewards->gift($body, $key));
  }

}
