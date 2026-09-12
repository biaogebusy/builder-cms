<?php

namespace Drupal\commerce_number_pattern\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Token;

/**
 * Hook implementations for Commerce Number Pattern.
 */
class CommerceNumberPatternTokenHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceNumberPatternTokenHooks object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   * @param \Drupal\Core\Utility\Token $token
   *   The token utility service.
   */
  public function __construct(
    protected readonly DateFormatterInterface $dateFormatter,
    protected TimeInterface $time,
    protected readonly Token $token,
  ) {
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $time = $this->time->getRequestTime();
    $year = $this->dateFormatter->format($time, 'custom', 'Y');
    $month = $this->dateFormatter->format($time, 'custom', 'm');
    $day = $this->dateFormatter->format($time, 'custom', 'd');

    $info = [];
    $info['types']['pattern'] = [
      'name' => $this->t('Pattern'),
      'needs-data' => 'pattern',
    ];
    $info['tokens']['pattern']['day'] = [
      'name' => $this->t('Day'),
      'description' => $this->t('The current day, with a leading zero. (%date)', ['%date' => $day]),
    ];
    $info['tokens']['pattern']['month'] = [
      'name' => $this->t('Month'),
      'description' => $this->t('The current month, with a leading zero. (%date)', ['%date' => $month]),
    ];
    $info['tokens']['pattern']['year'] = [
      'name' => $this->t('Year'),
      'description' => $this->t('The current year. (%date)', ['%date' => $year]),
    ];
    $info['tokens']['pattern']['date'] = [
      'name' => $this->t('Custom date'),
      'description' => $this->t('A date in a custom format. See <a href="http://php.net/manual/function.date.php">the PHP documentation</a> for details.'),
      'dynamic' => TRUE,
    ];
    $info['tokens']['pattern']['number'] = [
      'name' => $this->t('Number'),
      'description' => $this->t('The generated sequential number.'),
    ];

    return $info;
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];
    if ($type == 'pattern') {
      $time = $this->time->getRequestTime();
      // The tokens must not be cached due to the reliance on the current time.
      $bubbleable_metadata->setCacheMaxAge(0);

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'day':
            $replacements[$original] = $this->dateFormatter->format($time, 'custom', 'd');
            break;

          case 'month':
            $replacements[$original] = $this->dateFormatter->format($time, 'custom', 'm');
            break;

          case 'year':
            $replacements[$original] = $this->dateFormatter->format($time, 'custom', 'Y');
            break;

          case 'number':
            if (!empty($data['pattern']['number'])) {
              $replacements[$original] = $data['pattern']['number'];
            }
            break;
        }
      }

      if ($date_tokens = $this->token->findWithPrefix($tokens, 'date')) {
        foreach ($date_tokens as $name => $original) {
          $replacements[$original] = $this->dateFormatter->format($time, 'custom', $name);
        }
      }
    }

    return $replacements;
  }

  /**
   * Sets the time service.
   *
   * This is needed for tests.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function setTimeService(TimeInterface $time): void {
    $this->time = $time;
  }

}
