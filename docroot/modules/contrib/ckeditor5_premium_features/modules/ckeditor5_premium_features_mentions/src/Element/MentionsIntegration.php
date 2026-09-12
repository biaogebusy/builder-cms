<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_mentions\Element;

use Drupal\ckeditor5_premium_features_mentions\Utility\MentionSettings;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Defines the Text Format utility class for handling the Mention data.
 */
class MentionsIntegration {

  /**
   * Creates the mentions integration element instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current user.
   * @param \Drupal\ckeditor5_premium_features_mentions\Utility\MentionSettings $mentionSettings
   *   Mention settings helper.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected MentionSettings $mentionSettings,
  ) {}

  /**
   * Process the text_format form element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   * @param array $complete_form
   *   The form structure.
   *
   * @return array
   *   The element data.
   */
  public function processElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface || !$form_object->getEntity() instanceof EntityInterface) {
      // Do not process anything, the entity is missing.
      return $element;
    }

    if (!$this->currentUser->hasPermission('mention users')) {
      return $element;
    }

    $element['#attached']['drupalSettings']['ckeditor5Premium']['mentions']['minCharacter'] = $this->mentionSettings->getMentionMinimalCharactersCount();
    $element['#attached']['drupalSettings']['ckeditor5Premium']['mentions']['dropdownLimit'] = $this->mentionSettings->getMentionAutocompleteListLength();
    $element['#attached']['drupalSettings']['ckeditor5Premium']['mentions']['marker'] = $this->mentionSettings->getMentionsMarker();

    return $element;
  }

  /**
   * Process the text_format form element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   * @param array $complete_form
   *   The form structure.
   *
   * @return array
   *   The element data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function process(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $service = \Drupal::service('ckeditor5_premium_features_mentions.element.mentions_integration');
    return $service->processElement($element, $form_state, $complete_form);
  }

}
