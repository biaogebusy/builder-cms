<?php

namespace Drupal\charts\Plugin\views\display;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\Attachment;
use Drupal\views\ViewExecutable;

/**
 * Display plugin to attach multiple chart configurations to the same chart.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "chart_extension",
 *   title = @Translation("Chart attachment"),
 *   help = @Translation("Display that produces a chart."),
 *   theme = "views_view",
 *   contextual_links_locations = {""}
 * )
 */
class ChartsPluginDisplayChart extends Attachment {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['style_plugin']['default'] = 'chart';
    $options['inherit_yaxis'] = ['default' => '1'];

    // Set the default style plugin to 'chart'.
    $options['style']['contains']['type']['default'] = 'chart';
    $options['defaults']['default']['style'] = FALSE;

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    return $this->view->render($this->display['id']);
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    $categories['attachment'] = [
      'title' => $this->t('Chart settings'),
      'column' => 'second',
      'build' => ['#weight' => -10],
    ];
    $displays = array_filter($this->getOption('displays'));
    if (count($displays) > 1) {
      $attach_to = $this->t('Multiple displays');
    }
    elseif (count($displays) == 1) {
      $display = array_shift($displays);
      if ($display = $this->view->storage->getDisplay($display)) {
        $attach_to = $display['display_title'];
      }
    }
    if (!isset($attach_to)) {
      $attach_to = $this->t('Not defined');
    }
    $options['displays'] = [
      'category' => 'attachment',
      'title' => $this->t('Parent display'),
      'value' => $attach_to,
    ];

    $options['inherit_yaxis'] = [
      'category' => 'attachment',
      'title' => $this->t('Axis settings'),
      'value' => $this->getOption('inherit_yaxis') ? $this->t('Use primary Y-axis') : $this->t('Create secondary axis'),
    ];

    unset($options['attachment_position']);

  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'displays':
        $form['#title'] .= $this->t('Parent display');
        break;

      case 'inherit_yaxis':
        $form['#title'] .= $this->t('Axis settings');
        $form['inherit_yaxis'] = [
          '#title' => $this->t('Y-Axis settings'),
          '#type' => 'radios',
          '#options' => [
            1 => $this->t('Inherit primary of parent display'),
            0 => $this->t('Create a secondary axis'),
          ],
          '#default_value' => $this->getOption('inherit_yaxis'),
          '#description' => $this->t('In most charts, the x- and y-axis from the parent display are both shared with each attached child chart. However, if this chart is going to use a different unit of measurement, a secondary axis may be added on the opposite side of the normal y-axis. Only create a secondary y-axis on the first chart attachment. You can rearrange displays if needed.'),
        ];
        break;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    // It is very important to call the parent function here:
    parent::submitOptionsForm($form, $form_state);
    $section = $form_state->get('section');
    switch ($section) {
      case 'displays':
        $form_state->setValue($section, array_filter($form_state->getValue($section)));
        break;

      // @todo set isDefaulted to false by default.
      case 'inherit_arguments':
      case 'inherit_exposed_filters':
      case 'inherit_pager':
      case 'inherit_yaxis':
        $this->setOption($section, $form_state->getValue($section));
        break;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function attachTo(ViewExecutable $view, $display_id, array &$build) {

    $displays = $this->getOption('displays');
    if (empty($displays[$display_id])) {
      return;
    }

    if (!$this->access()) {
      return;
    }

  }

}
