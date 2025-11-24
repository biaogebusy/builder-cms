<?php

namespace Drupal\webform_rest\Plugin\rest\resource;

use Drupal\webform\Entity\Webform;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a resource for retrieving webform elements.
 *
 * @RestResource(
 *   id = "webform_rest_fields",
 *   label = @Translation("Webform Fields"),
 *   uri_paths = {
 *     "canonical" = "/webform_rest/{webform_id}/fields"
 *   }
 * )
 */
class WebformFieldsResource extends ResourceBase {

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenManager = $container->get('webform.token_manager');
    return $instance;
  }


  /**
   * Responds to GET requests, returns webform elements.
   *
   * @param string $webform_id
   *   Webform ID.
   *
   * @return \Drupal\rest\ResourceResponse
   *   HTTP response object containing webform elements.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws HttpException in case of error.
   */
  public function get($webform_id) {
    if (empty($webform_id)) {
      throw new BadRequestHttpException(t("Webform ID wasn't provided"));
    }

    // Load the webform.
    $webform = Webform::load($webform_id);

    // Basic check to see if something's returned.
    if ($webform) {

      // Return only the form elements.
      $elements = $webform->getElementsInitialized();
      $elements = $this->tokenManager->replace($elements, $webform);
      return new ModifiedResourceResponse($elements);
    }

    throw new NotFoundHttpException(t("Can't load webform."));

  }

}
