<?php

namespace Drupal\slick\Plugin\Field\FieldFormatter;

use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterTrait;
use Drupal\blazy\Plugin\Field\FieldFormatter\BlazyFormatterViewTrait;
use Drupal\Core\Field\FieldDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Trait common for slick formatters.
 */
trait SlickFormatterTrait {

  use BlazyFormatterTrait {
    injectServices as blazyInjectServices;
    getCommonFieldDefinition as blazyCommonFieldDefinition;
  }

  use BlazyFormatterViewTrait;

  /**
   * Returns the slick admin service shortcut.
   */
  public function admin() {
    return \Drupal::service('slick.admin');
  }

  /**
   * Injects DI services.
   */
  protected static function injectServices($instance, ContainerInterface $container, $type = '') {
    $instance = static::blazyInjectServices($instance, $container, $type);
    $instance->formatter = $instance->blazyManager = $container->get('slick.formatter');
    $instance->manager = $container->get('slick.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getFieldStorageDefinition()->isMultiple();
  }

  /**
   * {@inheritdoc}
   */
  protected function pluginSettings(&$blazies, array &$settings): void {
    // @todo remove post blazy:2.18.
    $blazies->set('namespace', 'slick')
      ->set('item.id', 'slide');
  }

  /**
   * {@inheritdoc}
   *
   * @todo remove post blazy:2.18.
   */
  public function getCommonFieldDefinition() {
    return ['namespace' => 'slick']
      + $this->blazyCommonFieldDefinition();
  }

}
