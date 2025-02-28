<?php

namespace Drupal\wechat_menu\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;

/**
 * Provides a delete form for wechat menu item.
 */
class WechatMenuItemDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->urlInfo();
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    //return $this->getCancelUrl();
	return Url::fromRoute('entity.wechat_menu_item.collection');
	//return 'entity.wechat_menu_item.collection';
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    return $this->t('The wechat menu item %title has been deleted.', array('%title' => $this->entity->label()));
  }

}
