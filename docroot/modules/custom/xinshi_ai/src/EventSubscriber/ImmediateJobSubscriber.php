<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\EventSubscriber;

use Drupal\xinshi_ai\Service\ImmediateJobRunner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 响应发出后(kernel.terminate)即时消费 image_job 队列。
 *
 * 仅在 controller 本请求内 schedule() 过才执行,避免每个请求都空跑队列。
 */
final class ImmediateJobSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ImmediateJobRunner $runner,
  ) {}

  public function onTerminate(TerminateEvent $event): void {
    if ($this->runner->isScheduled()) {
      $this->runner->run();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::TERMINATE => ['onTerminate', 0]];
  }

}
