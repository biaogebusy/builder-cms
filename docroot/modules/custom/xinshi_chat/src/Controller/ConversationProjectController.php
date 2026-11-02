<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_chat\Service\ConversationProject;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Confirms an idempotent association without exposing other project contents. */
final class ConversationProjectController extends ControllerBase {

  public function __construct(private readonly ConversationProject $projects) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_chat.conversation_project'));
  }

  public function link(string $uuid, string $project_uuid, Request $request): Response {
    if ($request->getContent() !== '') {
      throw new BadRequestHttpException('Project association does not accept a request body.');
    }
    $this->projects->link($uuid, $project_uuid);
    return new Response('', 204, ['Cache-Control' => 'private, no-store']);
  }

}
