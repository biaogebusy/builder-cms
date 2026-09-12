<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_todo_document_list\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to remove "disabled" attribute in the To-Do list elements.
 *
 * @Filter(
 *   id = "ckeditor5_plugin_pack_todo_list_filter",
 *   title = @Translation("CKEditor5 To-Do List: Removes the <b>disabled</b> attribute for each element in the To-Do list."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   weight = -100
 * )
 */
class FilterTodoList extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    $lists = $xpath->query('//ul[@class="todo-list"]');

    foreach ($lists as $todoList) {
      $inputs = $xpath->query('.//input', $todoList);

      foreach ($inputs as $input) {
        if ($input->hasAttribute('disabled')) {
          $input->removeAttribute('disabled');
        }
      }
    }
    $dom->saveHTML();
    $text = Html::serialize($dom);

    return new FilterProcessResult($text);
  }

}
