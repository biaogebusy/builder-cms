<?php

namespace Drupal\commerce_product\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;

/**
 * Field handler to present a button to copy variation link.
 */
#[ViewsField("commerce_product_variation_copy_link")]
class ProductVariationCopyLink extends ProductVariationViewLink {

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['output_url_as_text']['#access'] = FALSE;
    $form['text']['#access'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    $url = $this->getUrlInfo($row);
    if ($url) {
      return [
        '#theme' => 'commerce_copy_link',
        '#link' => $url->toString(),
        '#title' => $this->t('Copy variation link to clipboard'),
      ];
    }
    return '';
  }

}
