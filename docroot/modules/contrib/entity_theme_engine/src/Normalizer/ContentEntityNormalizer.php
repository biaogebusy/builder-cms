<?php

namespace Drupal\entity_theme_engine\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Cache\Cache;
use Drupal\system\Entity\Menu;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Menu\InaccessibleMenuLink;


class ContentEntityNormalizer extends NormalizerBase {

  /**
   * @var string[]
   */
  protected $supportedInterfaceOrClass = ['Drupal\Core\Entity\ContentEntityInterface'];

  /**
   * @var string[]
   */
  protected $format = ['twig_variable'];

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $label = '';
    try {
      $label = $entity->label();
    } catch(\Exception $e) {}
    $data = [
      'entity_id' => $entity->id(),
      'entity_uuid' => $entity->uuid(),
      'entity_label' => $label,
      'entity_url' => ($entity->id() && $entity->hasLinkTemplate('canonical'))?$entity->toUrl()->toString(TRUE)->getGeneratedUrl():"",
      'entity_type' => $entity->getEntityTypeId(),
      'entity_bundle' => $entity->bundle(),
      'entity_revision_id' => $entity->getRevisionId()
    ];
    $_cache = &drupal_static('twig_variables_entity',[]);
    if(!empty($_cache[$entity->getEntityTypeId()][$entity->id()])) {
      return $_cache[$entity->getEntityTypeId()][$entity->id()];
    }
    $cache = [
      'contexts' => $entity->getCacheContexts()?:[],
      'tags' => $entity->getCacheTags()?:[],
      'max-age' => $entity->getCacheMaxAge(),
    ];
    $data['#cache'] = $cache;
    $_cache[$entity->getEntityTypeId()][$entity->id()] = $data;
    if(isset($context['level']) && $context['level'] > 5) {
      return $data;
    };
    if($entity instanceof FieldableEntityInterface) {
      $fields = $entity->getFieldDefinitions();
      foreach ($fields as $field_name => $field_def) {
        if(array_key_exists($field_name, $data)) continue;
        if(in_array($field_name, [
          'nid', 'uuid', 'vid', 'revision_timestamp', 'revision_uid', 'revision_log',
          'revision_default', 'revision_translation_affected', '_deleted', '_rev', 'path',
          'revision_user', 'revision_created', 'roles', 'parent'
        ])) continue;
        $field_type = $field_def->getType();
        if (isset($context['#ignore_fields']) && in_array($field_name, $context['#ignore_fields'])) {
          continue;
        }
        $sub_context = $context;
        $sub_context['field_name'] = $field_name;
        $sub_context['field_type'] = $field_type;
        $sub_context['field_definition'] = $field_def;
        if($field_def->getFieldStorageDefinition()->getCardinality() == 1) {
          if($entity->get($field_name)->isEmpty()){
            $data[$field_name] = NULL;
          }else {
            $field_data = $this->serializer->normalize($entity->get($field_name)->get(0), $format, $sub_context);
            if(!empty($field_data['#cache'])) {
              $cache = $this->mergeCache($cache, $field_data['#cache']);
            }
            $data[$field_name] = $field_data;
          }
        }else {
          foreach ($entity->get($field_name) as $item) {
            $field_data = $this->serializer->normalize($item, $format, $sub_context);
            if(!empty($field_data['#cache'])) {
              $cache = $this->mergeCache($cache, $field_data['#cache']);
            }
            $data[$field_name]['items'][] = $field_data;
          }
        }
      }
    }
    $data['#cache'] = $cache;

    switch ($entity->getEntityTypeId()) {
      case 'media':
        $media_source = $entity->getSource()->getSourceFieldValue($entity);
        $file = $this->entityTypeManager->getStorage('file')->load($media_source);
        if($file && $file instanceof File) {
          if($entity->bundle() == 'image') {
            $data['styles'] = $this->getImageStylesVariables($file);
          }
          $media_source = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
        }
        $data['media_source'] = $media_source;
        $data['filesize'] = $file->filesize->value;
        break;
      case 'image':
        $data['styles'] = $this->getImageStylesVariables($entity);
        break;
      case 'taxonomy_vocabulary':
        $data['data'] = $this->getVocabularyData($entity);
        break;
      case 'taxonomy_term':
        $terms =$this->entityTypeManager->getStorage('taxonomy_term')->loadTree($entity->bundle(), $entity->id(), 1, true);
        $data['children'] = [];
        foreach ($terms as $term) {
          $data['children'] []= $this->normalize($term, $format);
        }
        break;
    }

    $_cache[$entity->getEntityTypeId()][$entity->id()] = $data;
    return $data;
  }
  /**
   *
   * @param array $a
   * @param array $b
   * @return string[][]|number[]
   */
  public function  mergeCache(array $a, array $b) {
    $cache = [];
    $cache['contexts'] = Cache::mergeContexts($a['contexts']?:[],$b['contexts']?:[]);
    $cache['tags'] = Cache::mergeTags($a['tags']?:[],$b['tags']?:[]);
    $cache['max-age'] = Cache::mergeMaxAges($a['max-age'],$b['max-age']);
    return $cache;
  }
  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, string $format = NULL, array $context = []): bool {
    if(in_array($format, $this->format) && parent::supportsNormalization($data, $format, $context)) {
      return TRUE;
    }
    return FALSE;
  }

  public function getImageStylesVariables(File $entity) {
    $variables = [];
    $styles = static::getStyles();
    foreach ($styles as $id => $style) {
      $variables[$id] = $style->buildUrl($entity->getFileUri());
    }
    return $variables;
  }
  public function getStyles() {
    $cache = &drupal_static(__METHOD__, []);
    if(empty($cache)) {
      $cache = $this->entityTypeManager->getStorage('image_style')->loadMultiple();
    }
    return $cache;
  }
  /**
   *
   * @param Vocabulary $vocabulary
   * @return array
   */
  public function getVocabularyData(Vocabulary $vocabulary) {
    return [];
  }
  /**
   *
   * @param Menu $menu
   * @return array
   */
  public function getMenuData(Menu $menu) {
    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks();

    $menu_active_trail = $this->menu_active_trail->getActiveTrailIds($menu->id());
    $parameters->setActiveTrail($menu_active_trail);

    $links = $this->menu_tree->load($menu->id(), $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $links = $this->menu_tree->transform($links, $manipulators);
    $data = static::processMenuLink($links);
    return $data;
  }
  /**
   *
   * @param array $links
   * @return array|NULL[][]|array
   */
  protected  function processMenuLink($links) {
    if(empty($links)) return [];
    $data = [];
    foreach ($links as $key => $item) {
      if($item->link instanceof InaccessibleMenuLink) continue;
      $data[$key] = [
        'url' => $item->link->getUrlObject()->toString(),
        'title' => $item->link->getTitle(),
        'options' => $item->link->getOptions(),
        'active' => $item->inActiveTrail,
      ];
      if($item->hasChildren) {
        $data[$key]['subtree'] = $this->processMenuLink($item->subtree);
      }
    }
    return $data;
  }
}
