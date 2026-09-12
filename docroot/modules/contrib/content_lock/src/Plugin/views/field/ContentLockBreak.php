<?php

namespace Drupal\content_lock\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\GeneratedLink;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to present a link to an entity.
 *
 * @group views_field_handlers
 *
 * @ViewsField("content_lock_break_link")
 */
#[ViewsField('content_lock_break_link')]
class ContentLockBreak extends FieldPluginBase {

  /**
   * Prepares link to the file.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $data
   *   The XSS safe string for the link text.
   * @param \Drupal\views\ResultRow $values
   *   The values retrieved from a single row of a view's query result.
   *
   * @return \Drupal\Core\GeneratedLink
   *   Returns the generated link.
   */
  protected function renderLink(string|MarkupInterface $data, ResultRow $values): GeneratedLink {
    $entity = $this->getEntity($values);
    $url = Url::fromRoute(
      'content_lock.break_lock.' . $entity->getEntityTypeId(),
      [
        'entity' => $entity->id(),
        'langcode' => $entity->language()->getId(),
        'form_op' => $values->content_lock_form_op ?? '*',
      ]
    );

    $break_link = Link::fromTextAndUrl($this->t('Break lock'), $url);
    return $break_link->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string|MarkupInterface {
    $value = $this->getValue($values);
    return $this->renderLink($this->sanitizeValue($value), $values);
  }

}
