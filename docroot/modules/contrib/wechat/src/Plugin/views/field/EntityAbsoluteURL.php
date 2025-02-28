<?php

namespace Drupal\wechat\Plugin\views\field;

use Drupal\views\Plugin\views\field\EntityLink;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to present a absolute url to an entity.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("entity_absolute_url")
 */
class EntityAbsoluteURL extends EntityLink {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    if($this->getEntity($row)){
	  $url_info = $this->getUrlInfo($row);
	  $url_info->setAbsolute();
	   return $url_info->toString();
	}
	else{
      return [];
	};
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row) {
    $template = $this->getEntityLinkTemplate();
    return $this->getEntity($row)->urlInfo($template);
  }

  /**
   * Returns the entity link template name identifying the link route.
   *
   * @returns string
   *   The link template name.
   */
  protected function getEntityLinkTemplate() {
    return 'canonical';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel() {
    return $this->t('view');
  }

}
