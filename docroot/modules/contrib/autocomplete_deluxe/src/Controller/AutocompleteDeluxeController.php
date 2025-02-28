<?php

namespace Drupal\autocomplete_deluxe\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Site\Settings;
use Drupal\system\Controller\EntityAutocompleteController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines a route controller for entity autocomplete form elements.
 */
class AutocompleteDeluxeController extends EntityAutocompleteController {

  /**
   * {@inheritdoc}
   */
  public function handleAutocomplete(Request $request, $target_type, $selection_handler, $selection_settings_key) {
    $matches = [];
    // Get the typed string from the URL, if it exists.
    $input = trim($request->query->get('q'));
    if (!empty($input)) {
      $typed_string = Tags::explode($input);
      $typed_string = array_pop($typed_string);
    }
    else {
      // Select without entering something.
      $typed_string = '';
    }

    // Selection settings are passed in as a hashed key of a serialized array
    // stored in the key/value store.
    $selection_settings = $this->keyValue->get($selection_settings_key, FALSE);
    if ($selection_settings !== FALSE) {
      $selection_settings_hash = Crypt::hmacBase64(serialize($selection_settings) . $target_type . $selection_handler, Settings::getHashSalt());
      if ($selection_settings_hash !== $selection_settings_key) {
        // Disallow access when the selection settings hash does not match the
        // passed-in key.
        throw new AccessDeniedHttpException('Invalid selection settings key.');
      }
    }
    else {
      // Disallow access when the selection settings key is not found in the
      // key/value store.
      throw new AccessDeniedHttpException();
    }

    $matches = $this->matcher->getMatches($target_type, $selection_handler, $selection_settings, $typed_string);

    $items = [];
    foreach ($matches as $item) {
      $items[$item['value']] = $item['label'];
    }

    $matches = $items;

    return new JsonResponse($matches);
  }

}
