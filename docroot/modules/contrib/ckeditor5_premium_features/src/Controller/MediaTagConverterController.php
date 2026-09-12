<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Controller;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\filter\Entity\FilterFormat;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Controller exposing an endpoint for rendering media entities.
 */
class MediaTagConverterController extends ControllerBase {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(
    protected RendererInterface $renderer,
    protected RequestStack $requestStack
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('request_stack')
    );
  }

  /**
   * API endpoint for rendering media tags.
   *
   * @param string $format
   *   Text editor format.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function decodeMediaTags(string $format, Request $request) {
    $entityTypes = $this->getMediaIdsWithTypes($request);
    $viewMode = $this->getMediaEmbedDefaultViewMode($format);

    if (empty($entityTypes) || empty($viewMode)) {
      return new JsonResponse([]);
    }

    $resultList = [];
    foreach ($entityTypes as $type => $ids) {
      $entities = $this->entityTypeManager()->getStorage($type)->loadByProperties([
        'uuid' => $ids,
      ]);
      $view_builder = $this->entityTypeManager()->getViewBuilder($type);

      /** @var \Drupal\media\Entity\Media $entity */
      foreach ($entities as $entity) {
        // Read all image file references from the media entity.
        $fileIds = [];
        $fileData = [];
        foreach ($entity->getFields() as $fieldName => $field) {
          if ($field->getFieldDefinition()->getType() === 'image') {
            foreach ($field->getIterator() as $key => $item) {
              $id = $item->get('target_id')->getValue();
              $fileIds[] = $id;
              $fileData[$fieldName][$key] = $id;
            }
          }
        }

        $imageFiles = $this->entityTypeManager()->getStorage('file')->loadMultiple($fileIds);

        $pre_render = $view_builder->view($entity, $viewMode);
        $pre_render["#pre_render"][] = function (array $build) use ($imageFiles, $fileData) {
          // Add custom attributes to the image field.
          foreach ($fileData as $fieldName => $items) {
            if (!isset($build[$fieldName]) || !is_array($build[$fieldName])) {
              continue;
            }
            foreach ($items as $key => $fid) {
              if (isset($imageFiles[$fid])) {
                $file = $imageFiles[$fid];
                $build[$fieldName][$key]['#item_attributes']['data-entity-type'] = 'file';
                $build[$fieldName][$key]['#item_attributes']['data-entity-uuid'] = $file->uuid();
              }
            }
          }
          return $build;
        };

        $viewRendered = $this->renderer->render($pre_render);

        if ($viewRendered) {
          $resultList[$entity->uuid()] = [
            'uuid' => $entity->uuid(),
            'rendered' => $viewRendered,
          ];
        }
      }
    }

    return new AjaxResponse(array_values($resultList));
  }

  /**
   * Returns a media default view used to render entity or NULL.
   *
   * @param string $format
   *   Text editor format.
   */
  protected function getMediaEmbedDefaultViewMode(string $format): ?string {
    /** @var \Drupal\filter\Entity\FilterFormat $filterFormat */
    $filterFormat = FilterFormat::load($format);
    if (!$filterFormat) {
      throw new BadRequestHttpException('No matching text format');
    }
    $perm = $filterFormat->getPermissionName();

    if (!$this->currentUser()->hasPermission($perm)) {
      throw new AccessDeniedHttpException('Missing permission to use specified format');
    }

    try {
      /** @var \Drupal\media\Plugin\Filter\MediaEmbed $filter */
      $filter = $filterFormat->filters('media_embed');
    }
    catch (PluginNotFoundException) {
      return NULL;
    }
    if (!$filter) {
      return NULL;
    }

    return $filter->settings['default_view_mode'];
  }

  /**
   * Returns a list of media IDs with types collected from current request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array
   *   An array where media type is a key, and values is a list of media IDs.
   */
  protected function getMediaIdsWithTypes(Request $request): array {
    $args = $request->request;

    if (empty($args->get('media'))) {
      throw new BadRequestHttpException('Missing required parameters');
    }

    $media = Json::decode($args->get('media'));
    $entityTypes = [];

    foreach ($media as $entityInfo) {
      $entityTypes[$entityInfo['type']][] = $entityInfo['id'];
    }

    return $entityTypes;
  }

}
