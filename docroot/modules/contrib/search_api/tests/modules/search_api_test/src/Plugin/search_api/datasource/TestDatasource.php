<?php

namespace Drupal\search_api_test\Plugin\search_api\datasource;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\search_api\Datasource\DatasourcePluginBase;
use Drupal\search_api_test\TestPluginTrait;

/**
 * Provides a datasource for testing purposes.
 *
 * @SearchApiDatasource(
 *   id = "search_api_test",
 *   label = @Translation("&quot;Test&quot; datasource"),
 *   description = @Translation("This is the <em>test datasource</em> plugin description."),
 * )
 */
class TestDatasource extends DatasourcePluginBase {

  use TestPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function getItemId(ComplexDataInterface $item) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    $data = $this->getReturnValue(__FUNCTION__, []);
    return array_intersect_key($data, array_flip($ids));
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLanguage(ComplexDataInterface $item) {
    return $this->getReturnValue(__FUNCTION__, LanguageInterface::LANGCODE_NOT_SPECIFIED);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    return $this->getReturnValue(__FUNCTION__, []);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    $remove = $this->getReturnValue(__FUNCTION__, FALSE);
    if ($remove) {
      $this->configuration = [];
    }
    return $remove;
  }

}
