<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Controller;

use Drupal\ckeditor5_premium_features\CKEditorPremiumPluginsCheckerTrait;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for CKEditor 5 Premium Features routes.
 */
class DrupalVersion extends ControllerBase {

  use CKEditorPremiumPluginsCheckerTrait;

  /**
   * Constructs a new DrupalVersion Controller.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(protected RequestStack $requestStack) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
    );
  }

  /**
   * Access handler.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user object.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Access result for current user.
   */
  public function access(AccountInterface $account): AccessResult {
    $editor = $this->requestStack->getCurrentRequest()?->attributes->get('editor');

    if (!$editor) {
      return AccessResult::forbidden('No editor specified.');
    }

    $permission = 'use text format ' . $editor->getFilterFormat()->id();
    return AccessResult::allowedIf($account->hasPermission($permission), "User doesn't have permission to create specified media entity.");
  }

  /**
   * Returns the current Drupal version. {major}.{minor} format.
   *
   * @return string
   *   The current Drupal version.
   */
  public function currentVersion(): AjaxResponse {
    $editor = $this->requestStack->getCurrentRequest()?->attributes->get('editor');

    if (!$this->hasPremiumFeaturesEnabled($editor->getSettings())) {
      return new AjaxResponse([
        'version' => 'n/a',
      ]);
    }

    $version = explode('.', \Drupal::VERSION);
    return new AjaxResponse([
      'version' => $version[0].'.'.$version[1],
    ]);
  }



}
