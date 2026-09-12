<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Controller;

use Drupal\ckeditor5_premium_features\ComposerInstaller\Installer;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller to install module dependencies.
 */
final class InstallerController extends ControllerBase {
  public function __construct(
    private readonly Installer $installer
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $installer = $container->get('ckeditor5_premium_features.installer');
    assert($installer instanceof Installer);

    return new static(
      $installer,
    );
  }

  public function install(Request $request): JsonResponse {
    $stage = $request->get('stage');
    return match ($stage) {
      'create' => $this->createStage(),
      'require' => $this->requireStage($request),
      'apply' => $this->applyStage($request),
      'post_apply' => $this->postApplyStage($request),
      'finish' => $this->destroyStage($request),
      default => $this->failedResponse('Invalid stage.'),
    };
  }

  private function createStage(): JsonResponse {
    if (!$this->installer->isAvailable()) {
      if ($this->installer->isInternalLock()) {
        try {
          $this->installer->destroy(TRUE);
        }
        catch (\Exception $e) {
          return $this->failedResponse('Failed to destroy previously initiated installation: ' . $e->getMessage());
        }
      }
      else {
        return $this->failedResponse('Installer is blocked by another installation process.');
      }
    }

    try {
      $stageId = $this->installer->create();
    }
    catch (\Exception $e) {
      // Handle the exception if needed.
      return $this->failedResponse('Failed to create stage: ' . $e->getMessage());
    }

    return new JsonResponse([
      'stage_id' => $stageId,
    ]);
  }

  private function requireStage(Request $request): JsonResponse {
    $stageId = $request->get('stage_id');
    $package = $request->get('package');
    try {
      $this->installer->claim($stageId);
      $this->installer->require([$package]);
    }
    catch (\Exception $e) {
      // Handle the exception if needed.
      return $this->failedResponse('Failed to require package: ' . $e->getMessage());
    }

    return new JsonResponse([
      'stage_id' => $stageId,
    ]);
  }

  private function applyStage(Request $request): JsonResponse {
    $stageId = $request->get('stage_id');
    try {
      $this->installer->claim($stageId);
      $this->installer->apply();
    }
    catch (\Exception $e) {
      // Handle the exception if needed.
      return $this->failedResponse('Failed to apply stage: ' . $e->getMessage());
    }

    return new JsonResponse([
      'stage_id' => $stageId,
    ]);
  }

  private function postApplyStage(Request $request): JsonResponse {
    $stageId = $request->get('stage_id');
    try {
      $this->installer->claim($stageId);
      $this->installer->postApply();
    }
    catch (\Exception $e) {
      // Handle the exception if needed.
      return $this->failedResponse('Failed to post apply stage: ' . $e->getMessage());
    }

    return new JsonResponse([
      'stage_id' => $stageId,
    ]);
  }

  private function failedResponse(string $message): JsonResponse {
    return new JsonResponse([
      'error' => TRUE,
      'message' => $message,
    ]);
  }

  public function destroyStage(Request $request): JsonResponse {
    $stageId = $request->get('stage_id');
    $stage = $request->get('stage');
    $package = $request->get('package');
    try {
      $this->installer->claim($stageId);
      $this->installer->destroy();
    }
    catch (\Exception $e) {
      // Handle the exception if needed.
      return $this->failedResponse('Failed to destroy stage: ' . $e->getMessage());
    }

    if ($stage === 'finish') {
      \Drupal::messenger()->addStatus($this->t('%package was installed successfully.', ['%package' => $package]));
    }

    return new JsonResponse([
      'stage_id' => $stageId,
    ]);
  }

}
