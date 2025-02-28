<?php

namespace Drupal\mie_demo_base\Utility;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\taxonomy\Entity\Term;

/**
 * Utility functions specific to mie_demo_base.
 */
class MieDemoBaseUtility {

  /**
   * Creates Drupal file from module files directory.
   *
   * @param string $file_name
   *   File name from module files directory.
   * @param string $module_path
   *   Module path that contains files directory.
   *
   * @return \Drupal\file\Entity\File
   *   Drupal File entity.
   */
  public static function createFile($file_name, $module_path = 'mie_demo_base') {
    $source = \Drupal::service('extension.list.module')->getPath('mie_demo_base') . '/files/' . $file_name;
    $destination = PublicStream::basePath();
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    try {
      $file_system->copy($source, $destination, FileSystemInterface::EXISTS_RENAME);
    }
    catch (\Exception $e) {
    }
    $demo_image_uri = 'public://' . $file_name;
    /** @var \Drupal\file\FileInterface $file */
    $demo_image = File::create([
      'uri' => $demo_image_uri,
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $demo_image->save();
    return $demo_image;
  }

  /**
   * Creates taxonomy term with special name and file.
   *
   * @param string $term_name
   *   New term name.
   * @param string $description
   *   (optional) New term description.
   * @param \Drupal\file\Entity\File $file
   *   (optional) File for the term field `field_mie_image`.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   Drupal Term entity.
   */
  public static function createMieDemoContentTerm($term_name, $description = NULL, File $file = NULL) {
    $values = [
      'vid' => 'mie_demo_content',
      'name' => $term_name,
      'field_mie_image' => [
        'target_id' => $file->id(),
      ],
    ];
    if (!empty($description)) {
      $values['description'] = [
        'value' => $description,
        'format' => '',
      ];
    }
    if (!empty($file)) {
      $values['field_mie_image'] = [
        'target_id' => $file->id(),
      ];
    }
    $term = Term::create($values);
    $term->save();
    return $term;
  }

  /**
   * Creates `mie-demo-base-menu` Drupal Menu Link Content.
   *
   * @param string $title
   *   Menu link title.
   * @param string $uri
   *   Menu link URL.
   * @param int $weight
   *   (optional) Menu link weight.
   * @param string $description
   *   (optional) Menu link description.
   * @param string $parent_uuid
   *   (optional) Menu link parent UUID.
   * @param string $view_mode
   *   (optional) Menu link view mode property.
   * @param bool $show_expanded
   *   (optional) Show as expanded menu link.
   * @param string $field_body_value
   *   (optional) Value for menu link body field.
   * @param \Drupal\taxonomy\Entity\Term[] $taxonomy_terms
   *   (optional) Taxonomy terms for attaching to menu link.
   * @param string $image_file_id
   *   (optional) File ID for image field.
   *
   * @return \Drupal\menu_link_content\Entity\MenuLinkContent
   *   Drupal Menu Link Content entity.
   */
  public static function createMieDemoBaseMenuMenuLinkContent($title, $uri, $weight = 0, $description = '', $parent_uuid = '', $view_mode = '', $show_expanded = FALSE, $field_body_value = '', array $taxonomy_terms = NULL, $image_file_id = NULL) {
    $values = [
      'bundle' => 'mie-demo-base-menu',
      'menu_name' => 'mie-demo-base-menu',
      'title' => $title,
      'expanded' => $show_expanded,
      'link' => [
        'uri' => $uri,
        'title' => $title,
      ],
      'weight' => $weight,
      'description' => $description,
      'field_body' => [
        'value' => $field_body_value,
        'format' => 'basic_html',
      ],
      'view_mode' => 'default',
    ];
    if (!empty($parent_uuid)) {
      $values['parent'] = 'menu_link_content:' . $parent_uuid;
    }
    if (!empty($view_mode)) {
      $values['view_mode'] = $view_mode;
    }
    if (!empty($taxonomy_terms)) {
      foreach ($taxonomy_terms as $taxonomy_term) {
        $values['field_mie_demo_content_terms'][]['target_id'] = $taxonomy_term->id();
      }
    }
    if (!empty($image_file_id)) {
      $values['field_image'] = [
        'target_id' => $image_file_id,
        'alt' => '',
        'width' => '',
        'height' => '',
      ];
    }
    $sample_link = MenuLinkContent::create($values);
    $sample_link->save();
    return $sample_link;
  }

}
