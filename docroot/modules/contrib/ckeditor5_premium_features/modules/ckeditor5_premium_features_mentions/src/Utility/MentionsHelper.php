<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_mentions\Utility;

/**
 * Class for collecting mentions data.
 */
class MentionsHelper {

  /**
   * Constructor.
   *
   * @param \Drupal\ckeditor5_premium_features_mentions\Utility\MentionSettings $mentionSettings
   *   Mention settings.
   */
  public function __construct(protected MentionSettings $mentionSettings) {
  }

  /**
   * Get mentions list detected in a body.
   *
   * @param string $body
   *   String body.
   */
  public function getMentions(string $body): array {
    $marker = $this->mentionSettings->getMentionsMarker();
    $regexp = '/data-mention="' . $marker . '([^"]+)"/';

    if (preg_match_all($regexp, $body, $matches)) {
      return $matches[1];
    }

    return [];
  }

}
