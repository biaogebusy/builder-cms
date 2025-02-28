<?php

namespace Drupal\metatag\TypedData;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\TypedData;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * A computed property for each meta tag.
 *
 * Required settings (below the definition's 'settings' key) are:
 *  - tag_name: The tag to be processed.
 *    Examples: "title", "description".
 *
 * @deprecated in metatag:8.x-1.23 and is removed from metatag:2.0.0. A replacement will be provided separately.
 *
 * @see https://www.drupal.org/node/3326104
 */
class IndividualTag extends TypedData {

  use DependencySerializationTrait;

  /**
   * Cached processed value.
   *
   * @var string
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function __construct(DataDefinitionInterface $definition, $name = NULL, TypedDataInterface $parent = NULL) {
    parent::__construct($definition, $name, $parent);

    if ($definition->getSetting('tag_name') === NULL) {
      throw new \InvalidArgumentException("The definition's 'tag_name' key has to specify the name of the meta tag to be processed.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if (isset($this->value)) {
      return $this->value;
    }

    // The Metatag plugin ID.
    $property_name = $this->definition->getSetting('tag_name');

    // The item is the parent.
    $item = $this->getParent();
    $entity = $item->getEntity();

    // Rendered values.
    $metatagManager = \Drupal::service('metatag.manager');
    $defaultTags = $metatagManager->tagsFromEntityWithDefaults($entity);
    if (!isset($defaultTags[$property_name])) {
      \Drupal::service('logger.factory')
        ->get('metatag')
        ->notice('No default for "%tag_name" - entity_type: %type, entity_bundle: %bundle, id: %id. See src/TypedData/Metatags.php.', [
          '%tag_name' => $property_name,
          '%type' => $entity->getEntityTypeId(),
          '%bundle' => $entity->bundle(),
          '%id' => $entity->id(),
        ]);
      return FALSE;
    }
    $tags = [
      $property_name => $defaultTags[$property_name],
    ];
    $values = $metatagManager->generateRawElements($tags, $entity);

    if (!empty($values)) {
      $all_tags = [];
      foreach (\Drupal::service('plugin.manager.metatag.tag')->getDefinitions() as $tag_name => $tag_spec) {
        $all_tags[$tag_name] = new $tag_spec['class']([], $tag_name, $tag_spec);
      }

      // Because the values are an array, loop through the output to to support
      // both single and multi-value tags.
      $tag = $all_tags[$property_name];
      if (!empty($values)) {
        $attribute_name = $tag->getHtmlValueAttribute();
        $attribute_values = [];
        foreach ($values as $value) {
          if (isset($value['#attributes'][$attribute_name])) {
            $attribute_values[] = $value['#attributes'][$attribute_name];
          }
        }
        $this->value = implode(' ', $attribute_values);
      }
    }

    return $this->value;
  }

}
