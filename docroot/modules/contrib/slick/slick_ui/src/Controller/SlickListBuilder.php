<?php

namespace Drupal\slick_ui\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\slick\Entity\SlickInterface;

/**
 * Provides a listing of Slick optionsets.
 */
class SlickListBuilder extends SlickListBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'slick_list_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label'       => $this->t('Optionset'),
      'breakpoints' => $this->t('Breakpoints'),
      'group'       => $this->t('Group'),
      'lazyload'    => $this->t('Lazyload'),
      'skin'        => $this->t('Skin'),
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    // Satisfy phpstan.
    if (!($entity instanceof SlickInterface)) {
      return parent::buildRow($entity);
    }

    $skins = $this->manager->skinManager()->getSkins()['skins'];
    $skin = $entity->getSkin();

    $row['label'] = Html::escape($entity->label());
    $row['breakpoints']['#markup'] = $entity->getBreakpoints();
    $row['group']['#markup'] = $entity->getGroup() ?: $this->t('All');
    $row['lazyload']['#markup'] = $entity->getSetting('lazyLoad') ?: $this->t('None');

    $markup = $skin;
    if (isset($skins[$skin]['description'])) {
      // No need to re-translate, as already translated at SlickSkin.php.
      $markup .= '<br />' . Html::escape($skins[$skin]['description']);
    }

    $row['skin']['#markup'] = $markup;

    return $row + parent::buildRow($entity);
  }

  /**
   * Adds some descriptive text to the slick optionsets list.
   *
   * @return array
   *   Renderable array.
   */
  public function render() {
    $manager = $this->manager;

    $build['description'] = [
      '#markup' => $this->t("<p>Manage the Slick optionsets. Optionsets are Config Entities.</p><p>By default, when this module is enabled, a single optionset is created from configuration. Install Slick example module to speed up by cloning them. Use the Operations column to edit, clone and delete optionsets.<br /><strong>Important!</strong> Avoid overriding Default optionset as it is meant for Default -- checking and cleaning. Use Duplicate instead. Otherwise messes are yours.<br />Slick doesn't need Slick UI to run. It is always safe to uninstall Slick UI once done with optionsets.</p>"),
    ];

    $availaible_skins = [];
    $skins = $manager->skinManager()->getSkins()['skins'];

    foreach ($skins as $key => $skin) {
      $name = $skin['name'] ?? $key;
      $group = Html::escape($skin['group'] ?? 'None');
      $provider = Html::escape($skin['provider'] ?? 'Slick');
      $description = Html::escape($skin['description'] ?? 'No description');

      $markup = '<h3>' . $this->t('@skin <br><small>Id: @id | Group: @group | Provider: @provider</small>', [
        '@skin' => $name,
        '@id' => $key,
        '@group' => $group,
        '@provider' => $provider,
      ]) . '</h3>';

      $markup .= '<p><em>&mdash; ' . $description . '</em></p>';

      $availaible_skins[$key] = [
        '#markup' => '<div class="messages messages--status">' . $markup . '</div>',
      ];
    }

    ksort($availaible_skins);
    $availaible_skins = ['default' => $availaible_skins['default']] + $availaible_skins;

    $settings = [];
    $settings['grid'] = 3;
    $settings['grid_medium'] = 2;
    $settings['style'] = 'column';

    $header = '<br><hr><h2>' . $this->t('Available skins') . '</h2>';
    $header .= '<p>' . $this->t('Some skin works best with a specific Optionset, and vice versa. Use matching names if found. Else happy adventure!') . '</p>';
    $build['skins_header']['#markup'] = $header;
    $build['skins_header']['#weight'] = 20;

    $build['skins'] = $manager->toGrid($availaible_skins, $settings);
    $build['skins']['#weight'] = 21;
    $build['skins']['#attached'] = $manager->attach($settings);
    $build['skins']['#attached']['library'][] = 'blazy/admin';

    $build[] = parent::render();
    return $build;
  }

}
