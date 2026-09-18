<?php

namespace Drupal\captcha\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal 7 captcha point source from database.
 *
 * @MigrateSource(
 *   id = "d7_captcha_points",
 *   source_module = "captcha"
 * )
 */
class CaptchaPoints extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('state'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('captcha_points', 'c')->fields('c');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'form_id' => $this->t('The name of the form'),
      'module' => $this->t('The captcha point providing module.'),
      'captcha_type' => $this->t('The captcha type.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['form_id']['type'] = 'string';
    return $ids;
  }

}
