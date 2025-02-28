<?php

namespace Drupal\memcache_admin\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Component\Render\HtmlEscapedText;
use Drupal\Core\Render\HtmlResponse;

/**
 * Memcache Admin Subscriber.
 */
class MemcacheAdminSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['displayStatistics'];
    return $events;
  }

  /**
   * Display statistics on page.
   */
  public function displayStatistics(ResponseEvent $event) {
    $user = \Drupal::currentUser();

    if ($user->id() == 0) {
      // Suppress for the above criteria.
    }
    else {
      $response = $event->getResponse();

      // Don't call theme() during shutdown if the registry has been rebuilt
      // (such as when enabling/disabling modules on admin/build/modules) as
      // things break.
      // Instead, simply exit without displaying admin statistics for this page
      // load.  See http://drupal.org/node/616282 for discussion.
      // @todo make sure this is not still a requirement.
      // @codingStandardsIgnoreStart
      // if (!function_exists('theme_get_registry') || !theme_get_registry()) {
      //   return;
      // }.
      // @codingStandardsIgnoreEnd
      // Try not to break non-HTML pages.
      if ($response instanceof HtmlResponse) {

        // This should only apply to page content.
        if (stripos((string) $response->headers->get('content-type'), 'text/html') !== FALSE) {
          $show_stats = \Drupal::config('memcache_admin.settings')->get('show_memcache_statistics');
          if ($show_stats && $user->hasPermission('access memcache statistics')) {
            $output = '';

            $memcache       = \Drupal::service('memcache.factory')->get(NULL, TRUE);
            $memcache_stats = $memcache->requestStats();
            if (!empty($memcache_stats['ops'])) {
              foreach ($memcache_stats['ops'] as $row => $stats) {
                $memcache_stats['ops'][$row][0] = new HtmlEscapedText($stats[0]);
                $memcache_stats['ops'][$row][1] = number_format($stats[1], 2);
                $hits                           = number_format($this->statsPercent($stats[2], $stats[3]), 1);
                $misses                         = number_format($this->statsPercent($stats[3], $stats[2]), 1);
                $memcache_stats['ops'][$row][2] = number_format($stats[2]) . " ($hits%)";
                $memcache_stats['ops'][$row][3] = number_format($stats[3]) . " ($misses%)";
              }

              $build = [
                '#theme'  => 'table',
                '#header' => [
                  $this->t('operation'),
                  $this->t('total ms'),
                  $this->t('total hits'),
                  $this->t('total misses'),
                ],
                '#rows'   => $memcache_stats['ops'],
              ];
              $output .= \Drupal::service('renderer')->renderRoot($build);
            }

            if (!empty($memcache_stats['all'])) {
              $build = [
                '#type'  => 'table',
                '#header' => [
                  $this->t('ms'),
                  $this->t('operation'),
                  $this->t('bin'),
                  $this->t('key'),
                  $this->t('status'),
                ],
              ];
              foreach ($memcache_stats['all'] as $row => $stats) {
                $build[$row]['ms'] = ['#plain_text' => $stats[0]];
                $build[$row]['operation'] = ['#plain_text' => $stats[1]];
                $build[$row]['bin'] = ['#plain_text' => $stats[2]];
                $build[$row]['key'] = [
                  '#separator' => ' | ',
                ];
                foreach (explode('\n', $stats[3]) as $akey) {
                  $build[$row]['key']['child'][]['#plain_text'] = $akey;
                }
                $build[$row]['status'] = ['#plain_text' => $stats[4]];
              }
              $output .= \Drupal::service('renderer')->renderRoot($build);
            }

            if (!empty($output)) {
              $response->setContent($response->getContent() . '<div id="memcache-devel"><h2>' . $this->t('Memcache statistics') . '</h2>' . $output . '</div>');
            }
          }
        }
      }
    }
  }

  /**
   * Helper function. Calculate a percentage.
   */
  private function statsPercent($a, $b) {
    if ($a == 0) {
      return 0;
    }
    elseif ($b == 0) {
      return 100;
    }
    else {
      return $a / ($a + $b) * 100;
    }
  }

}
