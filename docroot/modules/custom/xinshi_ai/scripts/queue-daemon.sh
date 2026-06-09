#!/bin/sh
#
# xinshi_ai 图片任务队列常驻消费者(queue.worker = daemon 时使用)。
#
# 由 supervisor / s6 拉起并在退出后自动重启;以 **PHP-FPM 的 web 用户**(通常 nginx)
# 运行,确保写入的 media 文件属主与前台一致,避免「destination directory not properly
# configured」这类属主/权限错配。
#
# drush queue:run 在队列排空时会立即返回,故用外层 while + sleep 做常驻:
#  - 队列有任务:在 --time-limit 窗口内持续消费,新任务近实时被领取;
#  - 队列空闲:快速退出 → sleep → 重试,新任务延迟 ≈ sleep + 一次 drush 引导。
#
# 用法(容器内): /var/www/html/docroot/modules/custom/xinshi_ai/scripts/queue-daemon.sh
set -eu

DRUPAL_ROOT="${DRUPAL_ROOT:-/var/www/html}"
DRUSH="${DRUSH:-$DRUPAL_ROOT/vendor/bin/drush}"
QUEUE="${XINSHI_AI_QUEUE:-xinshi_ai_image_job}"
TIME_LIMIT="${XINSHI_AI_QUEUE_TIME_LIMIT:-55}"
IDLE_SLEEP="${XINSHI_AI_QUEUE_IDLE_SLEEP:-1}"

cd "$DRUPAL_ROOT"

while true; do
  # || true:单轮处理异常不应拖垮守护进程,失败任务由 worker 内部重投 + 下一轮兜底。
  "$DRUSH" queue:run "$QUEUE" --time-limit="$TIME_LIMIT" || true
  sleep "$IDLE_SLEEP"
done
