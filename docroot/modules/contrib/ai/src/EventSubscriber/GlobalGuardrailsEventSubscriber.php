<?php

declare(strict_types=1);

namespace Drupal\ai\EventSubscriber;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Guardrail\AiGuardrailRepository;
use Drupal\ai\OperationType\InputInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Attaches globally configured guardrail sets to every AI input.
 *
 * Listens on PreGenerateResponseEvent at a high priority so that the
 * configured global guardrail sets are added to the input before the
 * GuardrailsEventSubscriber evaluates them. Any sets already attached
 * by the caller are preserved; global sets are prepended in config order.
 *
 * @see https://www.drupal.org/project/ai/issues/3584851
 */
class GlobalGuardrailsEventSubscriber implements EventSubscriberInterface {

  /**
   * Priority used so global sets attach before GuardrailsEventSubscriber runs.
   */
  public const PRIORITY = 100;

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly AiGuardrailRepository $guardrailRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => ['attachGlobalGuardrails', self::PRIORITY],
    ];
  }

  /**
   * Attach configured global guardrail sets to the event's input.
   */
  public function attachGlobalGuardrails(PreGenerateResponseEvent $event): void {
    $input = $event->getInput();
    if (!$input instanceof InputInterface) {
      return;
    }

    $ids = $this->configFactory->get('ai.settings')->get('global_guardrails') ?? [];
    if (!is_array($ids) || $ids === []) {
      return;
    }

    $globals = [];
    foreach ($ids as $id) {
      $id = (string) $id;
      if ($id === '') {
        continue;
      }
      $set = $this->guardrailRepository->getGuardrailSetById($id);
      if ($set !== NULL) {
        $globals[$set->id()] = $set;
      }
    }

    if ($globals === []) {
      return;
    }

    // Prepend globals so they evaluate the original input/output before any
    // caller-attached guardrail can rewrite it. On key collision ($globals
    // already contains a set id the caller also attached), the global wins
    // and sits at the front — the caller's ordering intent is intentionally
    // overridden by the site-wide configuration.
    $input->setGuardrailSets($globals + $input->getGuardrailSets());
  }

}
