<?php

namespace Drupal\ai_logging\Cron;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * The AI Log Pruning service.
 */
class AiLogPruning {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The watchdog logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructor for the pruning service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $logger) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
  }

  /**
   * The AI Log Pruning service, run once per day on cron.
   */
  public function pruneLogs() {
    $config = $this->configFactory->get('ai_logging.settings');
    $max_messages = $config->get('prompt_logging_max_messages');
    $prune_time = $config->get('prompt_logging_max_age');
    if ($max_messages) {
      // Prune by number of messages.
      $query = $this->entityTypeManager->getStorage('ai_log')->getQuery();
      $query->sort('created', 'DESC');
      // Get from max messages to the end - 10000 at a time.
      $query->range($max_messages, 10000);
      $query->accessCheck(FALSE);
      $ids = $query->execute();
      if (count($ids)) {
        $this->logger->get('ai_logging')->notice('Pruning AI logs on count, deleting @count logs.', ['@count' => count($ids)]);
        foreach ($ids as $id) {
          $this->entityTypeManager->getStorage('ai_log')->load($id)->delete();
        }
      }
    }

    if ($prune_time) {
      // Prune by age.
      $query = $this->entityTypeManager->getStorage('ai_log')->getQuery();
      // Its days.
      $query->condition('created', time() - ($prune_time * 86400), '<');
      $query->accessCheck(FALSE);
      $ids = $query->execute();
      if (count($ids)) {
        $this->logger->get('ai_logging')->notice('Pruning AI logs on time, deleting @count logs.', ['@count' => count($ids)]);
        foreach ($ids as $id) {
          $this->entityTypeManager->getStorage('ai_log')->load($id)->delete();
        }
      }
    }
  }

}
