<?php

namespace Drupal\commerce\Hook;

use Drupal\commerce\InboxMessage;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Hook implementations for Commerce.
 */
class CommerceThemeHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceThemeHooks object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $elementInfoManager
   *   The element info manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected DateFormatterInterface $dateFormatter,
    protected ElementInfoManagerInterface $elementInfoManager,
    protected RendererInterface $renderer,
    protected TimeInterface $time,
  ) {
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_dashboard_inbox' => [
        'variables' => [
          'unread_text' => '',
          'messages' => [],
        ],
        'initial preprocess' => static::class . ':preprocessCommerceDashboardInbox',
      ],
      'commerce_dashboard_management_links' => [
        'variables' => [
          'links' => [],
        ],
      ],
      'commerce_dashboard_metrics_item' => [
        'variables' => [
          'attributes' => [],
          'title' => '',
          'values' => [],
          'metric_value_attributes' => [],
        ],
      ],
      'commerce_dashboard_video_youtube' => [
        'variables' => [
          'youtube_id' => NULL,
          'autoplay' => TRUE,
        ],
      ],
      'commerce_copy_link' => [
        'variables' => [
          'link' => NULL,
          'title' => NULL,
        ],
      ],
    ];
  }

  /**
   * Prepares variables for the dashboard inbox.
   */
  public function preprocessCommerceDashboardInbox(array &$variables): void {
    $request_time = $this->time->getRequestTime();

    foreach ($variables['messages'] as &$message) {
      if (!$message instanceof InboxMessage) {
        continue;
      }
      // We cast the object to an stdClass() so we can dynamically assign
      // properties to it without triggering deprecations.
      $message = (object) (array) $message;
      // Set the time ago string shown in the inbox based on the send date.
      $send_date = $message->send_date;
      $time_ago = NULL;
      if ($send_date > ($request_time - 120)) {
        $time_ago = $this->t('just now');
      }
      elseif ($send_date > ($request_time - 3600)) {
        $minutes = round(($request_time - $send_date) / 60);
        $time_ago = $this->t('@minutes minutes ago', ['@minutes' => $minutes]);
      }
      elseif ($send_date > ($request_time - 86400)) {
        $hours = round(($request_time - $send_date) / 3600);
        $time_ago = $this->formatPlural($hours, '1 hour ago', '@count hours ago');
      }
      elseif ($send_date > ($request_time - (86400 * 7))) {
        $days = round(($request_time - $send_date) / 86400);
        $time_ago = $this->formatPlural($days, '1 day ago', '@count days ago');
      }
      $message->time_ago = $time_ago ?? $this->dateFormatter->format($send_date, 'custom', 'l, F j, Y');

      if (empty($message->cta_link)) {
        continue;
      }
      $message->link = [
        '#type' => 'link',
        '#title' => $message->cta_text,
        '#attributes' => [
          'class' => [
            'button',
            'button--small',
            'button--primary',
          ],
        ],
      ];
      // Identify whether a CTA is external or internal.
      if (str_starts_with($message->cta_link, 'http')) {
        $message->link['#url'] = Url::fromUri($message->cta_link);
        $message->link['#attributes']['class'][] = 'ext-link';
        $message->link['#attributes']['target'] = '_blank';
      }
      else {
        try {
          $message->link['#url'] = Url::fromUserInput($message->cta_link);
          if (str_contains($message->cta_link, 'admin/commerce/modal')) {
            $query = parse_url($message->cta_link, \PHP_URL_QUERY);
            parse_str($query, $query_params);
            if (isset($query_params['content'])) {
              $message->link['#url'] = Url::fromUserInput($query_params['content']);
            }
            $message->link += [
              '#attached' => [
                'library' => ['core/drupal.dialog.ajax'],
              ],
            ];
            $message->link['#attributes']['class'][] = 'use-ajax';
            $message->link['#attributes']['data-dialog-type'] = 'modal';
            $message->link['#attributes']['data-dialog-options'] = Json::encode([
              'width' => 880,
              'title' => $message->subject,
            ]);
          }
        }
        catch (\Exception $exception) {
          continue;
        }
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_menu_local_action')]
  public function preprocessMenuLocalAction(array &$variables): void {
    if (in_array('commerce-inbox', $variables['link']['#options']['attributes']['class'], TRUE)) {
      $variables['attributes']['class'][] = 'commerce-inbox-action-link-wrapper';
      $variables['#attached']['library'][] = 'commerce/local-actions';
    }
  }

}
