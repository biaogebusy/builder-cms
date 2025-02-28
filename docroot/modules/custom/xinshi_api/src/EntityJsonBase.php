<?php

namespace Drupal\xinshi_api;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\webform\Entity\Webform;

/**
 * Class EntityJsonBase
 * @package Drupal\xinshi_api
 */
class EntityJsonBase implements EntityJsonInterface {

  /**
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var string
   */
  protected $mode = 'json';

  /**
   * @var array
   */
  private $cacheTags = [];

  /**
   * EntityJsonBase constructor.
   * @param EntityInterface $entity
   * @param string $mode
   */
  function __construct(EntityInterface $entity, $mode = 'json') {
    $this->entity = $entity;
    $this->mode = $mode;
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->setCacheTags($entity->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function getContent() {
    // TODO: Implement getJson() method.
    $data = [];
    $build = $this->entityTypeManager->getViewBuilder($this->entity->getEntityTypeId())->view($this->entity, $this->mode);
    switch ($this->entity->getEntityTypeId()) {
      case 'node':
      case 'user':
      case 'commerce_product':
        \Drupal::service('entity_theme_engine.entity_widget_service')->entityViewAlter($build, $this->entity, $this->mode);
        break;
    }
    $this->addCacheTags($build['content']['#cache']['tags'] ?? []);
    unset($build['#prefix']);
    unset($build['#suffix']);
    $content = \Drupal::service('renderer')->render($build);
    if (($str = $content->jsonSerialize()) && $data = Json::decode(htmlspecialchars_decode($str))) {
      $this->setFullText($data);
    }
    return $data ? $data : [];
  }

  /**
   * Set Full text value.
   * @param $data
   */
  public function setFullText(&$data) {
    if (empty($data)) {
      return;
    }
    foreach ($data as $key => &$val) {
      if (is_array($val)) {
        if (isset($val['dataType']) && isset($val['data'])) {
          switch ($val['dataType']) {
            case 'full_text':
              if (isset($val['limit']) && is_numeric($val['limit'])) {
                $val = mb_substr(urldecode($val['data']), 0, $val['limit']);
              } else {
                $val = urldecode($val['data']);
              }
              break;
            case "json_encode":
              $val = json_decode(urldecode($val['data']));
              break;
            case "boolean":
              $val = !empty($val['data']);
              break;
            case "webform":
              $val = $this->getWebform($val['data']);
              break;
            case 'query_link':
              $url = $val['data'];
              $append = $val['append'] ?? [];
              $this->setFullText($append);
              $val = $append;
              if (UrlHelper::isValid($url)) {
                $options = UrlHelper::parse($url);
                $val['href'] = $options['path'];
                $val['queryParams'] = $options['query'];
              } else {
                $val['href'] = $val['data'];
              }
              break;
          }
        } else {
          $this->setFullText($val);
        }
      }
    }
  }

  /**
   * Set Full text value.
   * @param $data
   */
  private function urlDecodeValue(&$data) {
    foreach ($data as $key => &$val) {
      if (is_array($val)) {
        $this->urlDecodeValue($val);
      } elseif (is_string($val)) {
        $val = urldecode($val);
      }
    }
  }

  /**
   * Return cache tags.
   * @return array
   */
  public function getCacheTags() {
    return $this->cacheTags;
  }

  /**
   * Set cache tags.
   * @param array $cacheTags
   */
  public function setCacheTags($cacheTags) {
    $this->cacheTags = $cacheTags;
  }

  /**
   * Add cache tags.
   * @param $tag string|array
   */
  public function addCacheTags($tag) {
    if ($tag) {
      $this->cacheTags = array_unique(array_merge($this->cacheTags, is_array($tag) ? $tag : [$tag]));
    }
  }

  private function getWebform($webform_id) {
    if (empty($webform_id) || empty($webform = Webform::load($webform_id))) {
      return [];
    }
    $elements = [];
    foreach ($webform->getElementsDecodedAndFlattened() as $key => $item) {
      $element = [];
      $element["label"] = $item['#title'];
      $element["key"] = $key;
      if (isset($item['#placeholder'])) {
        $element['placeholder'] = $item['#placeholder'];
      }
      foreach ($item as $name => $val) {
        if (!in_array($name, ['#title', '#placeholder', '#type'])) {
          if (strpos($name, '#') === 0) {
            $name = substr($name, 1);
          }
          $element['params'][$name] = $val;
        }
      }
      switch ($item['#type']) {
        case 'textfield':
        case 'email':
          $element["type"] = "input";
          break;
        case 'textarea':
          $element["type"] = "textarea";
          if (isset($item['#rows'])) {
            $element['params']['matAutosizeMinRows'] = $item['#rows'];
          }
          break;
        case 'webform_terms_of_service':
          $element["type"] = "terms_of_service";
          $element['params']['url'] = Url::fromUserInput(str_replace('_', '-', '/' . $webform_id . '-' . $key))->toString();
          break;
        default:
          $element = [];
          break;
      }
      if ($element) {
        $elements[] = $element;
      }
    }
    return $elements;
  }
}
