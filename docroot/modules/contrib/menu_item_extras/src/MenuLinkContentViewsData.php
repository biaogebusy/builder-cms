<?php

namespace Drupal\menu_item_extras;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the taxonomy entity type.
 */
class MenuLinkContentViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();
    $data['menu_link_content_data']['table']['base']['title'] = $this->t('Menu Link Content');
    $data['menu_link_content_data']['table']['group'] = $this->t('Menu Link Content');

    // The parent field allows NULL, so specify that for views.
    $data['menu_link_content_data']['parent']['filter']['allow empty'] = TRUE;

    return $data;
  }

}
