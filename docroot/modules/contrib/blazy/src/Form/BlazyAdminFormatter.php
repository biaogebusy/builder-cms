<?php

namespace Drupal\blazy\Form;

use Drupal\blazy\BlazyDefault;

/**
 * Provides admin form specific to Blazy admin formatter.
 */
class BlazyAdminFormatter extends BlazyAdminFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array &$form, array $definition): void {
    parent::buildSettingsForm($form, $definition);

    /** @var \Drupal\blazy\BlazySettings $scopes */
    $scopes = $this->toScopes($definition);

    $this->openingForm($form, $definition);
    $this->basicImageForm($form, $definition);

    if ($scopes->form('grid') && !isset($form['grid'])) {
      // Blazy doesn't need complex grid with multiple groups.
      if ($scopes->get('namespace') == 'blazy') {
        $scopes->set('is.grid_simple', TRUE);
      }

      $this->gridForm($form, $definition);
    }

    if ($scopes->form('fieldable')) {
      $this->fieldableForm($form, $definition);
    }

    $this->closingForm($form, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function openingForm(array &$form, array &$definition): void {
    parent::openingForm($form, $definition);

    /** @var \Drupal\blazy\BlazySettings $scopes */
    $scopes = $this->toScopes($definition);
    $namespace = static::$namespace;
    $descriptions = $this->formatterDescriptions($scopes);

    if ($scopes->is('vanilla')) {
      $classes = ['full', 'tooltip-wide'];
      $form['vanilla'] = [
        '#type'        => 'checkbox',
        '#title'       => $this->t('Vanilla @namespace', ['@namespace' => $namespace]),
        '#description' => $descriptions['vanilla'],
        '#weight'      => -113,
        '#enforced'    => TRUE,
        '#attributes'  => ['class' => ['form-checkbox--vanilla']],
        '#wrapper_attributes' => $this->getTooltipClasses($classes),
      ];
    }

    if ($optionsets = $scopes->data('optionsets')) {
      $form['optionset'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Optionset'),
        '#options'     => $optionsets,
        '#enforced'    => TRUE,
        '#description' => $descriptions['optionset'],
        '#weight'      => -110,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fieldableForm(array &$form, array $definition): void {
    parent::fieldableForm($form, $definition);

    /** @var \Drupal\blazy\BlazySettings $scopes */
    $scopes = $this->toScopes($definition);
    $data = $scopes->get('data', []);
    $base_image = $this->baseForm($definition)['image'] ?? [];
    $descriptions = $this->formatterDescriptions($scopes);

    if (isset($data['images']) && $base_image) {
      $form['image'] = $base_image;
    }

    if (isset($data['thumbnails'])) {
      $form['thumbnail'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Thumbnail image'),
        '#options'     => $this->toOptions($data['thumbnails']),
        '#description' => $descriptions['thumbnail'],
      ];
    }

    if (isset($data['overlays'])) {
      $form['overlay'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Overlay media'),
        '#options'     => $this->toOptions($data['overlays']),
        '#description' => $descriptions['overlay'],
      ];
    }

    if (isset($data['titles'])) {
      // Ensures to not override Views content/ entity title, just formatters.
      if ($scopes->data('images') && !$scopes->is('_views')) {
        $scopes->set('data.titles.title', $this->t('Image Title'));
      }

      $form['title'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Title'),
        '#options'     => $this->toOptions($scopes->data('titles')),
        '#description' => $descriptions['title'],
      ];
    }

    $this->linkForm($form, $definition, $scopes);

    // Allows empty options to raise awareness of this option.
    if (isset($data['classes'])) {
      $form['class'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Item class'),
        '#options'     => $this->toOptions($data['classes']),
        '#description' => $descriptions['class'],
      ];
    }

    if (isset($form['caption'])) {
      $form['caption']['#description'] = $descriptions['caption'];
    }

    $weight = -90;
    foreach (BlazyDefault::viewsSettings() as $key) {
      if (isset($form[$key]) && !isset($form[$key]['#weight'])) {
        $form[$key]['#weight'] = --$weight;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function closingForm(array &$form, array $definition): void {
    /** @var \Drupal\blazy\BlazySettings $scopes */
    $scopes = $this->toScopes($definition);
    $descriptions = $this->formatterDescriptions($scopes);

    if ($scopes->is('caches')) {
      $form['cache'] = [
        '#type'        => 'select',
        '#title'       => $this->t('Cache'),
        '#options'     => $this->getCacheOptions(),
        '#weight'      => 98,
        '#enforced'    => TRUE,
        '#description' => $descriptions['cache'],
      ];
    }

    parent::closingForm($form, $definition);
  }

  /**
   * Returns formatter descriptions.
   *
   * @param \Drupal\blazy\BlazySettings $scopes
   *   The scopes being passed.
   *
   * @return array
   *   The form item descriptions.
   */
  protected function formatterDescriptions($scopes): array {
    $namespace = $scopes->get('namespace', 'blazy');

    $cache = $this->t(
      'Cache the rendered output as static HTML.
<ul>
  <li><strong>Permanent</strong>: cached until the next cron run.</li>
  <li><strong>Any number</strong>: expires after the selected time.</li>
</ul>

A working cron job is required to clear stale cache. Cached content is always refreshed on cron, regardless of expiration.

<br>Leave empty to disable caching.

<br><strong>Warning:</strong> Cached output is rendered as-is. Do not enable if it contains sensitive or contextual elements (e.g. edit links). Enable only after configuration is finalized.'
    );

    $caption = $this->t(
      'Enable one or more fields to be used as captions.
Selected fields will be wrapped and styled as captions.'
    );

    $overlay = $this->t(
      'Content displayed on top of the main stage, such as images, sliders, or other media.'
    );

    if ($scopes->is('_views')) {
      $cache .= ' ' . $this->t(
        'If updates are not visible, temporarily disable Views caching
(<strong>Advanced &gt; Caching</strong>).'
      );

      $overlay .= ' ' . $this->t(
        'If using Slick field formatter, enable
<strong>Use field template</strong> in its settings.'
      );
    }
    else {
      $caption .= ' ' . $this->t(
        'Ensure selected fields are visible in the chosen View mode.'
      );
    }

    return [
      'cache' => $cache,

      'caption' => $caption,

      'class' => $this->t(
        'Optional CSS class per item.
Useful for conditional styling (e.g. transparent images).

Field must output a string (Key or Label).

Supported types:
list text, string, title, term/entity label.'
      ),

      'optionset' => $this->t(
        'Enable the Optionset UI module to manage available optionsets.'
      ),

      'overlay' => $overlay,

      'thumbnail' => $this->t(
        'Leave empty to disable thumbnails or pagers.'
      ),

      'title' => $this->t(
        '<strong>Supported types</strong>:
Image title or string-based fields (Title, Link, etc.).

For entities, use formatters that output plain strings (ID or Label).

Unlike <strong>Caption fields</strong>, this is rendered as a heading
(overridable via
<code>hook_blazy_item_alter()</code> with
<code>blazies.item.title_tag</code>)
and wrapped with a dedicated class:
<strong>@class</strong>.',
        [
          '@class' => $namespace === 'blazy'
            ? 'blazy__caption--title'
            : $namespace . '__title',
        ]
      ),

      'vanilla' => $this->t(
        '<strong>Enable</strong> to render items without Blazy processing.
<ul>
  <li>Outputs raw formatter markup.</li>
  <li>Disables most @module features (layouts, grids, etc.).</li>
  <li>Use when custom formatting is required.</li>
  <li>Issues caused by enabling this option are not supported and
      considered custom works.</li>
</ul>

<strong>Disable</strong> to use consistent markup and advanced features.',
        [
          '@module' => $namespace,
        ]
      ),
    ];
  }

}
