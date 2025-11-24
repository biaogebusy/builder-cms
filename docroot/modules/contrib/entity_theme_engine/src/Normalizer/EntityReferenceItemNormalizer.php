<?php

namespace Drupal\entity_theme_engine\Normalizer;



use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Entity\TranslatableInterface;

class EntityReferenceItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = [
    'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem',
  ];

  /**
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menu_active_trail;

  /**
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menu_tree;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, MenuActiveTrailInterface $menu_active_trail, MenuLinkTreeInterface $menu_tree) {
    $this->entityTypeManager = $entityTypeManager;
    $this->menu_active_trail = $menu_active_trail;
    $this->menu_tree = $menu_tree;
  }
  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $data = parent::normalize($field, $format, $context);
    if(empty($field->entity)) {
      return $data;
    }
    $entity = $field->entity;
    $_cache = &drupal_static('twig_variables_entity',[]);
    if(!empty($_cache[$entity->getEntityTypeId()][$entity->id()])) {
      return $_cache[$entity->getEntityTypeId()][$entity->id()];
    }
    // Set the entity in the correct language for display.
    if ($entity instanceof TranslatableInterface) {
      $entity = \Drupal::service('entity.repository')->getTranslationFromContext($entity);
    }
    $sub_context = [
      'entity_widget' => $context['entity_widget'] ?? '',
      'entity' => $entity,
      'attach_mode' => $context['attach_mode'] ?? '',
      'level' => ($context['level'] ?? 0) + 1,
    ];
    if(isset($context['#no_render'])) {
      $sub_context['#no_render'] = $context['#no_render'];
    }
    if(isset($context['#ignore_fields'])) {
      $sub_context['#ignore_fields'] = $context['#ignore_fields'];
    }
    if($variables = $this->serializer->normalize($entity, $format, $sub_context)) {
      $data = array_merge($data, $variables);
    }
    if(empty($context['attach_mode']) && !empty($entity) && empty($context['#no_render'])) {
      try {
        $data['render'] = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity);
      } catch (\Exception $e) {}
    }
    switch ($entity->getEntityTypeId()) {
      case 'menu':
        $data['data'] = $this->getMenuData($entity);
        $data['#cache']['contexts'][] = 'user.permissions';
        $data['#cache']['contexts'][] = 'route.menu_active_trails:' . $entity->id();
        $data['#cache']['tags'][] = 'config:system.menu.' . $entity->id();
        break;
    }
    return $data;
  }
}
