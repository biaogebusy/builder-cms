<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\ckeditor5_premium_features_mentions\Utility\MentionsHelper;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service helping with mentions submodule integration.
 */
class MentionsIntegrator {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Service container.
   */
  public function __construct(protected ModuleHandlerInterface $moduleHandler,
                              protected ContainerInterface $container) {
  }

  /**
   * Checks if the Mentions module is enabled.
   */
  public function isMentionInstalled(): bool {
    return \Drupal::moduleHandler()->moduleExists('ckeditor5_premium_features_mentions');
  }

  /**
   * Returns MentionsHelper service if Mentions module is enabled.
   */
  public function getMentionHelperService(): ?MentionsHelper {
    if (!$this->isMentionInstalled()) {
      return NULL;
    }

    return $this->container->get('ckeditor5_premium_features_mentions.mentions_helper');
  }

}
