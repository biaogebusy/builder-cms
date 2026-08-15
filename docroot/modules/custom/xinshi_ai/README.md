# Xinshi AI

AI 任务(图片 / 视频 / 音频生成等)的统一后端。提供内容类型、自定义 HTTP 路由、SSE 进度流,以及一层「业务 Provider」,把前端的生成请求异步落地为 Drupal 实体。

当前已落地能力:**文生图**(`text_to_image`)与**图生图 / 图片编辑**(`image_edit`)。其余任务类型(chat / 语音等)的模型已进注册中心,但任务执行管线尚未实现。

---

## 1. 架构总览

```
前端 SPA
  │  POST /api/v3/image-jobs               (创建任务,拿 uuid + eventTicket)
  │  GET  /api/v3/image-jobs/{uuid}/events (SSE 订阅进度,ticket 鉴权)
  │  GET  /api/v3/ai/models                (拉模型注册中心)
  ▼
ImageJobController ──► JobLifecycleService ──► image_job 节点 (status=queued)
  │                                              │
  │  入队 xinshi_ai_image_job                     │
  ▼                                              ▼
ImageJobWorker (cron / drush queue:run)      EventStreamService (cache 事件流)
  │                                              ▲
  ▼                                              │ publish 进度事件
AiTaskManager ─► ImageGeneration / ImageEdit ───┘
  │
  ▼
ProviderResolver ─► drupal/ai Provider (xinshi / custom) ─► 上游网关
  │
  ▼
MediaUploadService ─► media:image + image_asset 节点
```

核心约定:**任务不自己做 HTTP / 重试**,那是 `drupal/ai` Provider 的职责;任务只负责 `validate / execute / cancel` 与状态机推进。

---

## 2. 依赖

模块依赖见 `xinshi_ai.info.yml`。关键外部依赖:

- `ai` + `ai_provider_openai`:底层 Provider 框架(本模块的网关 Provider 继承自 `OpenAiProvider`)。
- `node` / `media` / `file`:任务与产物的实体载体。
- `jsonapi` / `jsonapi_extras`:对外只读暴露 `image_job` / `image_asset`。
- `xinshi_views`:后台管理列表依赖的视图基建。

---

## 3. 数据模型(内容类型)

两个节点 bundle,安装时由 `config/install/` 自动导入。

### `image_job` — 一次生成请求

| 字段 | 说明 |
| --- | --- |
| `field_job_kind` | `text_to_image` / `image_edit` —— 决定走哪个 AiTask 插件 |
| `field_platform` | `xinshi` / `custom` |
| `field_model` | 模型 id(custom 平台改从 `field_params.model` 取) |
| `field_prompt` / `field_negative_prompt` | 提示词 |
| `field_params` | 透传给上游的生成参数 JSON(n / size / response_format / quality 等;custom 还含 endpoint / api_key / model) |
| `field_n_requested` / `field_n_succeeded` | 请求张数 / 已成功张数 |
| `field_input_image` | 图生图的源图(media 引用) |
| `field_assets` | 产出的 `image_asset` 引用列表 |
| `field_status` | 状态机:`queued → running → partial → succeeded / failed / cancelled` |
| `field_status_reason` / `field_error_code` | 失败原因与归一错误码 |
| `field_started_at` / `field_completed_at` / `field_latency_ms` | 观测时间戳 |
| `field_provider_request_id` / `field_prompt_revised` | 上游回填的元数据 |
| `field_cost_tokens` / `field_input_tokens` / `field_output_tokens` | 计量字段(暂未写入) |

### `image_asset` — 单张产出图片

引用源 `image_job`(`field_job`)与底层 `media:image`(`field_asset_image`),携带 `field_index` / `field_seed` / `field_width` / `field_height` / `field_starred` / `field_meta`。

---

## 4. HTTP 路由

定义见 `xinshi_ai.routing.yml`。

| 方法 | 路径 | 鉴权 | 说明 |
| --- | --- | --- | --- |
| POST | `/api/v3/image-jobs` | oauth2 / cookie + `create xinshi_ai image job` | 创建任务,返回 `202 {uuid, status, nRequested, eventTicket}` |
| POST | `/api/v3/image-jobs/{uuid}/cancel` | oauth2 / cookie + `cancel xinshi_ai image job` | 软取消(置终态 cancelled) |
| GET | `/api/v3/image-jobs/{uuid}/events` | **ticket**(非用户登录) | SSE 进度流 |
| GET | `/api/v3/ai/models` | `access content` | 模型注册中心(只读,剔除后端内部字段与禁用条目) |
| GET | `/api/v3/ai/manage/models` | oauth2 / cookie + `administer xinshi_ai` | 管理端全量注册中心(含禁用的平台与模型) |
| POST | `/api/v3/ai/models` | oauth2 / cookie + `administer xinshi_ai` | 新增模型 |
| PATCH | `/api/v3/ai/models/{id}` | oauth2 / cookie + `administer xinshi_ai` | 部分更新模型;`{"enabled": false}` 即禁用 |

任务与资产的**读取**走 JSON:API(`jsonapi_extras` 暴露,resource config 见 `config/optional/`)。`ImageJobNormalizer` 在 `format=json` 的读响应里把 `field_assets` 内联展开为带图片 URL 的资产对象,使列表单次请求即拿到缩略图。

后台管理 UI:`/admin/config/xinshi/ai`(设置)、`/admin/config/xinshi/ai/models`(模型 CRUD)。

---

## 5. 模型注册中心

`xinshi_ai.models` 配置(`config/install/xinshi_ai.models.yml`)是 AI 任务的**单一事实源**,描述「平台」与「模型」:

- **平台**(`platforms`):`xinshi`(信使统一网关,代理转发各厂商——含阿里通义千问,前端无需 API key)、`custom`(用户运行时自带 endpoint + key + model)。
- **模型**(`models`):带 `capabilities`(chat / reasoning / image / image-edit)、`max_n`、`sizes` 等约束。

`ModelRegistryService` 读取并缓存(随 `config:xinshi_ai.models` 失效),提供能力过滤与校验;后台模型管理通过 `saveModel` / `deleteModel` 改写配置。注意:**网关 base_url / api_key 不在这里**,在 `xinshi_ai.settings` 的 `gateway` 段。

模型条目支持 `enabled: false` 禁用(键缺省为启用):禁用后 `/api/v3/ai/models` 不再返回,任务提交校验(`validateModelCapability` / `isValid`)也会拒绝,但记录保留可随时重新启用。除后台 UI 外,管理前台可直接调用 §4 的管理 API(`ModelManageController`)新增与启停模型,写的是同一份配置。前端对接文档(字段契约 / 错误码 / 联调示例)见 [`docs/model-management-api.md`](docs/model-management-api.md)。

---

## 6. AI 任务插件

`#[AiTask]` 属性插件,放在 `src/Plugin/AiTask/`,由 `AiTaskManager` 按 `field_job_kind` 发现并实例化。

- `AiTaskBase` 注入业务服务(provider resolver / lifecycle / media / event stream / registry / error mapper),提供 `validateModelCapability()` 与 `effectiveModel()`。
- `AiImageTaskBase` 实现共享的图片落地管线 `runImagePipeline()`:`markStarted → 调 Provider → 落 media + asset → markCompleted`,每阶段推 SSE 事件;失败统一经 `ProviderErrorMapper` 归一错误码并抛 `TaskExecutionException`(由 worker 决定重试 / 终态)。
- `ImageGenerationTask`(`text_to_image`)/ `ImageEditTask`(`image_edit`)只实现各自的 `validate` 与参数组装。

新增任务类型:加一个 `#[AiTask(... jobKind: 'xxx')]` 插件即可,无需改 Controller。

---

## 7. Provider 解析

`ProviderResolver` 按 `field_platform` 映射到 `drupal/ai` Provider:

- `xinshi` → `XinshiGatewayAiProvider`(继承 `OpenAiProvider`,把 base URL 指向 `gateway.base_url`(自动补 `/v1`),API key 取 `gateway.api_key`)。各厂商(含阿里通义千问)由网关按 model id 转协议。
- `custom` → `CustomAiProvider`:用户单次提交的 `endpoint / api_key / model` 经 `setConfiguration()` 运行时注入,**不持久化**,且在 `loadClient()` 里从 payload 中 `unset` 以防泄入上游请求。

超时在 `createInstance` 阶段就注入(Provider 的 Guzzle client 构造时锁定 timeout),缺省 `gateway.request_timeout`(180s),远高于 ai 模块默认的 60s,以适配上游生图耗时。

两个 Provider 都用 `OpenAiCompatibleImageEditTrait` 补齐 `OpenAiProvider` 缺失的 `image_to_image`(`/v1/images/edits`)。

---

## 8. 异步执行与重试

`ImageJobWorker`(QueueWorker id `xinshi_ai_image_job`,cron 每轮 60s)消费队列:

- 任务已 `cancelled` 或不存在 → 直接丢弃。
- `TaskExecutionException` 且错误码**可重试**(`rate_limit` / `provider_5xx` / `provider_unavailable`)且未超 `MAX_RETRIES=2` → 重新入队(attempt+1)。
- 否则 → `markFailed` 并推 `failed` 事件。

> ⚠️ `DatabaseQueue` 不支持延迟入队,重试为**立即重投**(无退避)。若需 Retry-After 退避,改用 `advancedqueue` / `queue_unique`。

### 消费方式(`queue.worker`)

为避免「创建任务 → 下一次 cron」之间卡在 `queued`,提供两种即时消费方式,由 `xinshi_ai.settings:queue.worker` 控制(后台 `/admin/config/xinshi/ai` 可切):

- **`daemon`(默认,生产推荐)**:不在 web 进程处理。由 supervisor / s6 常驻拉起 `scripts/queue-daemon.sh`(内部循环跑 `drush queue:run`),**以 PHP-FPM 的 web 用户运行**,使写入的 media 文件属主与前台一致。
- **`cron` / `immediate`**:创建任务的请求结束后(`kernel.terminate`,见 `ImmediateJobRunner` + `ImmediateJobSubscriber`)在 **PHP-FPM 进程内**即时排空队列。开发便利,但会占住一个 worker 直到任务完成,且该进程用户须能写 `public://xinshi_ai/`。

> ⚠️ 切勿用 **root** 跑 drush/cron 去写 media 目录:若 web 用户与之不一致,先建的目录属主为 root,web 进程后续无写权限,会报 *destination directory ... is not properly configured*。统一用 web 用户消费即可规避。

supervisor 配置示例(`user` 改成实际的 PHP-FPM 用户,如 `nginx` / `www-data`):

```ini
[program:xinshi_ai_queue]
command=/var/www/html/docroot/modules/custom/xinshi_ai/scripts/queue-daemon.sh
user=nginx
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=70            ; > queue-daemon 的 TIME_LIMIT,让本轮 drush 优雅收尾
stdout_logfile=/www/logs/xinshi_ai_queue.log
redirect_stderr=true
```

无论哪种方式,cron 始终作为兜底(QueueWorker `cron: 60s`)。

队列也可手动消费:`drush queue:run xinshi_ai_image_job`。

---

## 9. SSE 进度流

- `EventStreamService`:基于 `cache.default`(走数据库,跨进程可见)的 append-only 事件流,键 `xinshi_ai:events:{jobUuid}`,带递增 `seq`,TTL 1h。事件类型:`status` / `asset_ready` / `completed` / `failed`。
- `ImageJobEventsController::stream`:长轮询读取并以 `text/event-stream` 推送,支持 `?cursor=` 断点续传,最长存活 1200s,`Cache-Control: no-store`。
- **鉴权用 ticket 而非用户登录**:`EventTicketService` 在创建任务时签发 HMAC-SHA256 票据(`base64url("{expiresAt}.{uid}.{sig}")`,签名用站点 private key,TTL 默认 300s),`accessByTicket()`(`_custom_access`)校验 `?ticket=`。

---

## 10. 缓存正确性

`ImageJobCacheSubscriber` 对 `image_job` 的 JSON:API 读响应与 `image_jobs` 视图页面/导出强制 `Cache-Control: no-store, private`。

原因:`image_job` 是随时变化的状态资源,若继承全局 `page.max_age` 的 `max-age=3600, public`,会被浏览器/CDN 缓存住中间态(如 `queued`),实体转终态后这些 HTTP 缓存不认 Drupal cache tag;且 `public` 用在权限受控的管理列表上还会泄露特权页面。

---

## 11. 配置

设置见 `config/install/xinshi_ai.settings.yml`(schema 见 `config/schema/`):

| 配置段 | 关键项 |
| --- | --- |
| `gateway` | `base_url`(默认 `https://ai.builder.design`)、`api_key`、`request_timeout` |
| `image` | `default_n` |
| `event_ticket` | `ttl` |
| `queue` | `worker`(daemon / cron;详见 §8 消费方式) |
| `sse` | `heartbeat_seconds` / `max_lifetime_seconds` |
| `observability` | `exporters` / `capture_content` / `ingest_token_env` / `otel` |

---

## 12. 权限

定义见 `xinshi_ai.permissions.yml`。安装时(`hook_install`)给 `authenticated` 角色授予 `create` / `cancel` 图片任务权限。

| 权限 | 说明 |
| --- | --- |
| `create xinshi_ai image job` | 提交新图片任务 |
| `cancel xinshi_ai image job` | 取消图片任务 |
| `administer xinshi_ai` | 管理网关凭据、默认参数、模型注册中心(restricted) |

---

## 13. 产物落地

`MediaUploadService` 把上游返回的图片写入 `public://xinshi_ai/YYYY-MM/`,创建托管 `file` 与 `media:image`(source 字段 `field_media_image`),再由 `JobLifecycleService::createAsset` 建 `image_asset` 并回填 job(追加引用、`n_succeeded+1`、推进 `partial`)。

---

## 14. 源码导航

```
src/
├── Attribute/AiTask.php           # 任务插件属性
├── AiTaskManager.php              # 按 jobKind 发现任务插件
├── AiTaskBase.php                 # 任务基类(注入服务 + 校验)
├── AiImageTaskBase.php            # 图片管线(generate/edit 共享)
├── Plugin/AiTask/                 # ImageGenerationTask / ImageEditTask
├── Plugin/AiProvider/             # XinshiGateway / Custom + image-edit trait
├── Plugin/QueueWorker/            # ImageJobWorker(异步 + 重试)
├── Controller/                    # ImageJob / ImageJobEvents / ModelRegistry / ModelManage / ModelAdmin
├── Service/                       # Lifecycle / EventStream / EventTicket / ModelRegistry / ProviderResolver / ProviderErrorMapper / MediaUpload
├── Normalizer/ImageJobNormalizer.php   # json 读响应内联 assets
├── EventSubscriber/ImageJobCacheSubscriber.php  # 强制 no-store
└── Form/                          # SettingsForm / ModelForm / ModelDeleteForm

config/install/    # 内容类型、字段、settings、模型注册中心、视图
config/optional/   # JSON:API resource config(模块装好后需手动激活)
definitions/       # drupal/ai 操作类型的默认参数模板
```
