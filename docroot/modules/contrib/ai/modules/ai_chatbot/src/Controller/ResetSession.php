<?php

namespace Drupal\ai_chatbot\Controller;

use Drupal\Core\Access\AccessException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_assistant_api\AiAssistantApiRunner;
use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Creates a controller to reset chat sessions.
 */
class ResetSession extends ControllerBase {

  /**
   * Number of allowed attempts to reset the session before locking.
   */
  const FLOOD_THRESHOLD = 3;

  /**
   * Constructs a new ResetSession object.
   */
  public function __construct(
    protected AiAssistantApiRunner $aiAssistantApiRunner,
    protected FloodInterface $flood,
  ) {
  }

  /**
   * Dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_assistant_api.runner'),
      $container->get('flood'),
    );
  }

  /**
   * Resets the sessions of the chatbot for a thread for the user.
   *
   * @param string $assistant_id
   *   The assistant id to reset.
   * @param string $thread_id
   *   The thread id to reset.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Result of the operation.
   */
  public function resetSession(string $assistant_id, string $thread_id) {
    $eventName = 'ai_chatbot.reset_session';
    if (!$this->flood->isAllowed($eventName, self::FLOOD_THRESHOLD)) {
      return new JsonResponse(['success' => FALSE], 429);
    }
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant */
    $assistant = $this->entityTypeManager()->getStorage('ai_assistant')->load($assistant_id);
    if (!$assistant) {
      return new JsonResponse([
        'success' => FALSE,
      ]);
    }
    $this->aiAssistantApiRunner->setAssistant($assistant);
    try {
      $new_thread_id = $this->aiAssistantApiRunner->resetThread($thread_id);
    }
    catch (AccessException) {
      $this->flood->register($eventName);
      return new JsonResponse(['success' => FALSE], 429);
    }
    catch (ResourceNotFoundException) {
      $this->flood->register($eventName);
      return new JsonResponse(['success' => FALSE], 404);
    }

    return new JsonResponse([
      'success' => TRUE,
      'thread_id' => $new_thread_id,
    ]);
  }

}
