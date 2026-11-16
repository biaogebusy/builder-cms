<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Service\SpeechRecognitionClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Authenticated microphone input; recordings and provider credentials are never persisted here. */
final class SpeechRecognitionController extends ControllerBase {

  public function __construct(private readonly SpeechRecognitionClient $client) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_ai.speech'));
  }

  /** Report current availability or transcribe one bounded PCM body. */
  public function handle(Request $request): JsonResponse {
    $headers = ['Cache-Control' => 'private, no-store'];
    try {
      if ($request->isMethod('GET')) {
        return new JsonResponse(['available' => $this->client->available()], 200, $headers);
      }
      if (!$request->isMethod('POST')) throw new HttpException(405, 'speech_method_not_allowed');
      if ($request->headers->get('Content-Type') !== 'audio/wav') {
        throw new HttpException(415, 'speech_invalid_audio');
      }
      // A raw body avoids PHP's multipart file limit; the upstream request still uses multipart.
      $stream = $request->getContent(TRUE);
      $audio = stream_get_contents($stream, SpeechRecognitionClient::MAX_BYTES + 1);
      if ($audio === FALSE) throw new HttpException(400, 'speech_invalid_audio');
      return new JsonResponse($this->client->transcribe($audio), 200, $headers);
    }
    catch (HttpException $error) {
      return new JsonResponse(['code' => $error->getMessage()], $error->getStatusCode(), $headers);
    }
    catch (\Throwable) {
      return new JsonResponse(['code' => 'speech_unavailable'], 503, $headers);
    }
  }

}
