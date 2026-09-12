# Entity Construction Kit (ECK)
The Entity Construction Kit (ECK) builds upon the entity system to create a
flexible and extensible data modeling system both with a UI for site builders,
and with useful abstractions (classes, plugins, etc) to help developers use
entities with ease.

ECK allows the creation and management of entity types with custom properties;
adding bundles to entity types; and fields to bundles, with the help of the
Field UI module.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/eck).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/eck).


## Requirements
This module requires no modules outside of Drupal core.

## Installation
Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration
- Install and enable Entity Construction Kit module.
  [Entity Construction Kit](https://www.drupal.org/project/eck)
- Go to `/admin/structure/eck` to add and configure a new custom entity type.

## Usage

### Creating entities programmatically

```php
use Drupal\eck\Entity\EckEntity;

// Using the static create method.
$entity = EckEntity::create([
  'entity_type' => $eck_entity_type,
  'type' => $eck_entity_bundle,
]);

// Using the entity type manager.
$entity = \Drupal::entityTypeManager()
  ->getStorage($eck_entity_type)
  ->create([
    'type' => $eck_entity_bundle,
  ]);
  
// Setting values and saving the entity.
$entity->setOwner($account);
$entity->set('title', $the_title);
$entity->set('field_some_string', $something);
$entity->save();
```
