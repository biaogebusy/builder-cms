<?php

namespace Drupal\panelizer_test\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $collection->get('panelizer.wizard.add')
      ->setRequirements([
        '_panelizer_default_access' => 'TRUE',
        '_permission' => 'administer panelizer',
      ]);

    $collection->get('panelizer.wizard.add.step')
      ->setRequirements([
        '_permission' => 'administer panelizer',
      ]);

    $collection->get('panelizer.wizard.edit')
      ->setRequirements([
        '_permission' => 'administer panelizer',
      ]);

    $collection->get('panelizer.wizard.step.context.add')
      ->setRequirements([
        '_permission' => 'administer panelizer',
      ]);

    $collection->get('panelizer.wizard.step.context.edit')
      ->setRequirements([
        '_permission' => 'administer panelizer',
      ]);

    $collection->get('panelizer.wizard.step.context.delete')
      ->setRequirements([
        '_permission' => 'administer panelizer',
      ]);
  }

}
