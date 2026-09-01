<?php

namespace Drupal\xinshi_api\Plugin\rest\resource;

use Symfony\Component\HttpFoundation\Request;

/**
 * Lists the webforms available to the builder.
 *
 * webform_rest exposes a single webform's fields and accepts submissions, but
 * has no endpoint to enumerate the webforms; the builder's widget picker needs
 * that list to offer them as insertable components.
 *
 * @RestResource(
 *   id = "xinshi_api_webform_rest",
 *   label = @Translation("Webform list"),
 *   uri_paths = {
 *     "canonical" = "/api/v3/webform"
 *   }
 * )
 */
class WebformResource extends XinshibResourceBase {

  /**
   * Returns the webforms the current user may read, as a flat list.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Each item carries id, title, description, category and status.
   */
  public function get(Request $request) {
    $this->addCacheTags(['config:webform_list']);
    $data = [];
    // The module does not hard depend on webform; answer with an empty list
    // instead of fataling when the resource is enabled without it.
    if (!$this->entityTypeManager->hasDefinition('webform')) {
      return $this->getResponse($data);
    }
    /** @var \Drupal\webform\WebformInterface $webform */
    foreach ($this->entityTypeManager->getStorage('webform')->loadMultiple() as $webform) {
      // Templates are starting points for new webforms, never live forms.
      // On a non-HTML request 'view' maps to webform configuration access,
      // which is what reading a form's metadata to embed it needs.
      if ($webform->isTemplate() || !$webform->access('view')) {
        continue;
      }
      $this->addCacheTags($webform->getCacheTags());
      $categories = $webform->get('categories') ?: [];
      $data[] = [
        'id' => $webform->id(),
        'title' => $webform->label(),
        'description' => strip_tags((string) $webform->getDescription()),
        'category' => reset($categories) ?: '',
        'status' => $webform->isOpen(),
      ];
    }
    return $this->getResponse($data);
  }

}
