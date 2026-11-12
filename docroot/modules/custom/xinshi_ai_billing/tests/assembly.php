<?php

declare(strict_types=1);

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\xinshi_credits\XinshiCreditsServiceProvider;
use Drupal\xinshi_ai_billing\AiBilling;

/** Model a restart using the real provider and fresh adapters in an isolated site. */
function fixtureFinance(): array {
  $container = new ContainerBuilder();
  $container->setParameter('container.modules', \Drupal::getContainer()->getParameter('container.modules'));
  (new \Drupal\xinshi_ai_runtime\XinshiAiRuntimeServiceProvider())->register($container);
  (new XinshiCreditsServiceProvider())->alter($container);
  return $container->getParameter('xinshi_site_finance');
}

function fixtureBilling(): AiBilling {
  return new AiBilling(\Drupal::database(), fixtureFinance()['chat_billing'],
    \Drupal::service('xinshi_credits.context'), \Drupal::service('xinshi_credits.lifecycle'),
    \Drupal::service('xinshi_ai_billing.profiles'), \Drupal::time(), \Drupal::service('uuid'),
    \Drupal::currentUser(), \Drupal::entityTypeManager());
}
