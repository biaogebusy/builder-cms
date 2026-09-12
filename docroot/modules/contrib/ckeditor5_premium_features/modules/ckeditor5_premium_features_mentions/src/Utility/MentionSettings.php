<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_mentions\Utility;

use Drupal\ckeditor5_premium_features_mentions\Form\SettingsForm;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Class for accessing Mention config values.
 */
class MentionSettings {

  /**
   * Config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $mentionSettings;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->mentionSettings = $configFactory->get(SettingsForm::MENTION_SETTINGS_ID);
  }

  /**
   * Returns mentions marker config.
   */
  public function getMentionsMarker(): string {
    return $this->mentionSettings->get('mention_marker') ?? '#';
  }

  /**
   * Returns mentions minimal character count config.
   */
  public function getMentionMinimalCharactersCount(): int {
    return (int) ($this->mentionSettings->get('mention_min_character') ?? 1);
  }

  /**
   * Returns mentions autocomplete list length config.
   */
  public function getMentionAutocompleteListLength(): int {

    return (int) ($this->mentionSettings->get('mention_dropdown_limit') ?? 4);
  }

  /**
   * Returns whether to use static permission condition for user query.
   */
  public function useStaticPermissionCondition(): bool {
    return (bool) ($this->mentionSettings->get('use_static_permission_condition') ?? FALSE);
  }

}
