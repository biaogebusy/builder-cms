<?php

namespace Drupal\simple_oauth\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;

/**
 * Plugin implementation of the 'oauth2_scope_reference' widget.
 *
 * @FieldWidget(
 *   id = "oauth2_scope_reference",
 *   label = @Translation("OAuth2 scope reference widget"),
 *   field_types = {
 *     "oauth2_scope_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class Oauth2ScopeReferenceWidget extends OptionsButtonsWidget {}
