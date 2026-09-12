<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_mentions\Controller;

use Drupal\ckeditor5_premium_features_mentions\DataProvider\MentionDataProvider;
use Drupal\ckeditor5_premium_features_mentions\Utility\MentionSettings;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller handling mention requests.
 */
class MentionAutocompleteController extends ControllerBase {

  /**
   * Constructor.
   *
   * @param \Drupal\ckeditor5_premium_features_mentions\DataProvider\MentionDataProvider $mentionsProvider
   *   User data provider.
   * @param \Drupal\ckeditor5_premium_features_mentions\Utility\MentionSettings $mentionSettings
   *   Mention settings.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(
    protected MentionDataProvider $mentionsProvider,
    protected MentionSettings $mentionSettings,
    protected RequestStack $requestStack
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ckeditor5_premium_features_mentions.data_provider.mentions'),
      $container->get('ckeditor5_premium_features_mentions.mention_settings'),
      $container->get('request_stack')
    );
  }

  /**
   * Method returning a json response with users matching query criteria.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function annotation() {
    $args = $this->requestStack->getCurrentRequest()->query;

    if (empty($args->get('query'))) {
      return new JsonResponse([]);
    }

    /** @var \Drupal\user\Entity\User[] $matchedUsers */
    $matchedUsers = $this->mentionsProvider->getPrivilegedEditors(
      $args->get('query'),
      $this->getDropdownLimit()
    );

    $resultList = [];
    foreach ($matchedUsers as $user) {
      $resultList[] = [
        'id' => $this->getMentionMarker() . $user->getDisplayName(),
        'link' => $user->toUrl(),
      ];
    }

    return new AjaxResponse($resultList);
  }

  /**
   * Returns a marker character used for starting annotations.
   */
  protected function getMentionMarker(): string {
    return $this->mentionSettings->getMentionsMarker();
  }

  /**
   * Returns the maximum number of suggestions displayed.
   */
  protected function getDropdownLimit(): int {
    return $this->mentionSettings->getMentionAutocompleteListLength();
  }

}
