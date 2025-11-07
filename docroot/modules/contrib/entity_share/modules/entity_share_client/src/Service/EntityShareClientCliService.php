<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Component\Utility\Timer;
use Drupal\entity_share_client\ImportContext;

/**
 * Service to ease the usage of CLI tools.
 *
 * @package Drupal\entity_share_client
 *
 * @internal This service is not an api and may change at any time.
 */
class EntityShareClientCliService {

  /**
   * Drupal\Core\StringTranslation\TranslationManager definition.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $stringTranslation;

  /**
   * The import service.
   *
   * @var \Drupal\entity_share_client\Service\ImportServiceInterface
   */
  protected $importService;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\entity_share_client\Service\ImportServiceInterface $import_service
   *   The import service.
   */
  public function __construct(
    TranslationInterface $string_translation,
    ImportServiceInterface $import_service
  ) {
    $this->stringTranslation = $string_translation;
    $this->importService = $import_service;
  }

  /**
   * Handle the pull interaction.
   *
   * @param string $remote_id
   *   The remote website id to import from.
   * @param string $channel_id
   *   The remote channel id to import.
   * @param string $import_config_id
   *   The import config id to import with.
   * @param \Symfony\Component\Console\Style\StyleInterface|\ConfigSplitDrush8Io $input_output
   *   The $io interface of the cli tool calling.
   * @param callable $translate
   *   The translation function akin to t().
   */
  public function ioPull($remote_id, $channel_id, $import_config_id, $input_output, callable $translate) {
    Timer::start('io-pull');
    $import_context = new ImportContext($remote_id, $channel_id, $import_config_id);
    $this->importService->importChannel($import_context);
    $batch =& batch_get();
    if ($batch) {
      drush_backend_batch_process();
      Timer::stop('io-pull');
      $input_output->success($translate('Channel successfully pulled. Execution time @time ms.', [
        '@time' => Timer::read('io-pull'),
      ]));
    }
  }

}
