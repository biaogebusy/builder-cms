<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\xinshi_ai\Service\ApiKeyVault;
use Drupal\xinshi_ai\Service\SupplierConfiguration;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\xinshi_ai\Service\FigmaConnector;
use Drupal\xinshi_ai\Service\HarnessSettings;
use Drupal\xinshi_ai\Service\PromptRewriteClient;
use Drupal\xinshi_ai_runtime\Service\ServiceIdentity;

/**
 * Xinshi AI 模块全局配置:/admin/config/xinshi/ai。
 *
 * 模型 / 平台清单不在这里管理(走 xinshi_ai.models,详见模块文档 §5)。
 */
final class SettingsForm extends ConfigFormBase {

  private const CONFIG_NAME = 'xinshi_ai.settings';

  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager,
    protected SupplierConfiguration $suppliers, protected ApiKeyVault $supplierKeys) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('config.factory'), $container->get('config.typed'),
      $container->get('xinshi_ai.supplier_configuration'), $container->get('xinshi_ai.supplier_keys'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'xinshi_ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::CONFIG_NAME);

    $form['gateway'] = [
      '#type' => 'details',
      '#title' => $this->t('信使网关'),
      '#open' => TRUE,
    ];
    $form['gateway']['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('网关 base URL'),
      '#description' => $this->t('OpenAI 兼容网关地址，CMS 与 Node 共用此地址，未包含版本路径时自动补 /v1。'),
      '#default_value' => $config->get('gateway.base_url'),
      '#required' => TRUE,
    ];
    $form['gateway']['api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('网关 API key'),
      '#maxlength' => 4096,
      '#description' => $this->t('留空保留已保存密钥，密钥加密保存，不进入配置导出，也不回填到浏览器。settings.php 的 xinshi_ai.supplier_keys 覆盖后台密钥。'),
    ];
    $form['gateway']['key_configured'] = [
      '#type' => 'item', '#title' => $this->t('密钥状态'),
      '#markup' => $this->suppliers->hasKey('xinshi') ? $this->t('已配置') : $this->t('未配置'),
    ];
    $form['gateway']['clear_gateway_key'] = [
      '#type' => 'checkbox', '#title' => $this->t('清除已保存的网关密钥'),
      '#description' => $this->t('清除后平台调用不可用，部署覆盖值仍由 settings.php 控制。'),
    ];
    $form['gateway']['request_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('上游请求超时(秒)'),
      '#description' => $this->t('调用网关生图的 HTTP 超时。上游生图可能耗时近百秒,默认 180。'),
      '#min' => 1,
      '#default_value' => $config->get('gateway.request_timeout'),
      '#required' => TRUE,
    ];

    $form['aliyun'] = [
      '#type' => 'details', '#title' => $this->t('阿里云直连对话'), '#tree' => TRUE,
      '#description' => $this->t('保留 aliyun 平台的 OpenAI 兼容对话连接，配置地址与密钥后供 Node 使用，不添加模型菜单或图片协议。'),
      'base_url' => ['#type' => 'url', '#title' => $this->t('对话 API 地址'), '#maxlength' => 2048,
        '#default_value' => $config->get('suppliers.aliyun.base_url') ?? ''],
      'api_key' => ['#type' => 'password', '#title' => $this->t('阿里云 API key'), '#maxlength' => 4096,
        '#description' => $this->t('填写完整 OpenAI 兼容版本路径，密钥留空保留已保存值。')],
      'configured' => ['#type' => 'item', '#title' => $this->t('密钥状态'),
        '#markup' => $this->suppliers->hasKey('aliyun') ? $this->t('已配置') : $this->t('未配置')],
      'clear_key' => ['#type' => 'checkbox', '#title' => $this->t('清除已保存的阿里云密钥')],
    ];

    $harness = HarnessSettings::normalize($config->get('harness'));
    $form['harness'] = [
      '#type' => 'details',
      '#title' => $this->t('对话任务（Harness）'),
      '#description' => $this->t('若对话服务设置了对应环境变量，则以环境变量为准。移除对应启动参数后，后台设置才会生效。'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['harness']['tools'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('业务工具'),
    ];
    $form['harness']['tools']['pages_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('启用页面草稿工具'),
      '#description' => $this->t('允许 AI 读取、创建、追加组件和删除本人草稿；仍需相应页面权限，写入仍需用户确认。关闭后仍可核验已提交操作。对应 CHAT_CREATE_PAGE_ENABLED。'),
      '#default_value' => $harness['tools']['pages_enabled'],
    ];
    $form['harness']['tools']['enabled'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('允许使用的工具'),
      '#description' => $this->t('取消勾选即可停用工具。勾选后仍需用户权限，页面草稿工具还需开启上方总开关，写入仍需确认。停用不会妨碍核验已提交操作。'),
    ];
    foreach (HarnessSettings::TOOL_GROUPS as $id => $group) {
      $form['harness']['tools']['enabled'][$id] = [
        '#type' => 'checkboxes',
        '#title' => $this->t($group['label']),
        '#options' => array_map(fn($label) => $this->t($label), $group['tools']),
        '#default_value' => array_values(array_diff(array_keys($group['tools']), $harness['tools']['disabled'])),
      ];
    }
    $documentTypes = \Drupal::service('xinshi_ai.product_documents')->availableContentTypes();
    $documents = $harness['mcp']['product_documents'];
    $form['harness']['mcp'] = ['#type' => 'container'];
    $form['harness']['mcp']['product_documents'] = [
      '#type' => 'fieldset', '#title' => $this->t('产品资料来源（MCP）'),
      '#description' => $this->t('使用本站已发布资料生成页面，并检查当前用户的查看权限。启用 Xinshi Knowledge 模块后，通过 Search API 检索正文和已解析的知识库附件。资料读取不会创建或发布页面。'),
      'enabled' => [
        '#type' => 'checkbox', '#title' => $this->t('启用产品资料读取'),
        '#default_value' => $documents['enabled'],
      ],
      'content_types' => [
        '#type' => 'checkboxes', '#title' => $this->t('允许作为资料的内容类型'),
        '#description' => $this->t('仅列出具有文本正文 body 字段的类型。可选择知识库、产品介绍、文章或案例；附件只从知识库专用字段读取，不读取未发布修订。更改资料范围后，等待 Search API 后台索引完成。'),
        '#options' => $documentTypes, '#default_value' => $documents['content_types'],
      ],
    ];
    $figma = $config->get('harness.mcp.figma') ?? [];
    $form['harness']['mcp']['figma'] = [
      '#type' => 'fieldset', '#title' => $this->t('Figma 设计稿'),
      '#description' => $this->t('在此统一配置 Figma OAuth 应用。用户通过 Figma 登录授权自己的账号，再从对话生成页面或组件。应用密钥和用户令牌仅由 Drupal 保存和使用，不需要在 Node 配置 Figma 密钥。'),
      'enabled' => [
        '#type' => 'checkbox', '#title' => $this->t('启用 Figma 设计稿读取'),
        '#default_value' => $figma['enabled'] ?? FALSE,
      ],
      'client_id' => [
        '#type' => 'textfield', '#title' => $this->t('Figma Client ID'),
        '#maxlength' => 512, '#default_value' => $figma['client_id'] ?? '',
      ],
      'client_secret' => [
        '#type' => 'password', '#title' => $this->t('Figma Client Secret'), '#maxlength' => 4096,
        '#description' => $this->t('留空保留已保存密钥。密钥加密保存在 Drupal，不进入配置导出。Figma 应用需启用 file_content:read 和 current_user:read 权限。'),
      ],
      'clear_secret' => [
        '#type' => 'checkbox', '#title' => $this->t('清除已保存的 Figma Client Secret'),
        '#description' => $this->t('清除密钥时需同时关闭 Figma 读取，或填写新密钥。'),
      ],
      'redirect_uri' => [
        '#type' => 'url', '#title' => $this->t('Figma OAuth 回调地址'), '#maxlength' => 2048,
        '#default_value' => $figma['redirect_uri'] ?? '',
        '#description' => $this->t('用户访问的信使源地址加 /chat/figma/callback，与 Figma OAuth App 登记值完全一致。正式环境使用 HTTPS；localhost 和 127.0.0.1 开发环境允许 HTTP。'),
      ],
    ];
    $form['harness']['web_search'] = [
      '#type' => 'fieldset', '#title' => $this->t('DeepSeek 工具'),
      '#description' => $this->t('联网搜索和语音识别共用此密钥。新会话默认开启联网搜索，用户可以按会话关闭。语音识别费用由站点承担，只记录用量。'),
      'speech_enabled' => [
        '#type' => 'checkbox', '#title' => $this->t('启用语音识别'),
        '#default_value' => $config->get('speech.enabled') === TRUE,
        '#description' => $this->t('默认关闭，启用后需配置下方密钥。此开关由 CMS 控制，关闭语音识别不影响联网搜索。'),
      ],
      'api_key' => [
        '#type' => 'password', '#title' => $this->t('DeepSeek API Key'), '#maxlength' => 4096,
        '#description' => $this->t('留空保留已保存密钥。密钥加密保存，不进入配置导出，也不返回浏览器。'),
      ],
      'configured' => [
        '#type' => 'item', '#title' => $this->t('密钥状态'),
        '#markup' => \Drupal::service('xinshi_ai.web_search_credentials')->has()
          ? $this->t('已配置') : $this->t('未配置'),
      ],
      'clear_key' => ['#type' => 'checkbox', '#title' => $this->t('清除已保存的 DeepSeek API Key'),
        '#description' => $this->t('清除后，联网搜索和语音识别均不可用。')],
    ];
    $form['harness']['task_limits'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('任务限制'),
      '#description' => $this->t('仅影响新建任务。已有任务在审批、继续执行和服务重启后，仍保留原上限及已用次数。'),
    ];
    $form['harness']['task_limits']['max_model_calls'] = [
      '#type' => 'number',
      '#title' => $this->t('每个任务最多模型调用次数'),
      '#description' => $this->t('包含规划、执行、评审、工具内部调用及显式重试。达到上限后停止发起新的模型请求。对应 CHAT_TASK_MAX_MODEL_CALLS。'),
      '#default_value' => $harness['task_limits']['max_model_calls'],
      '#min' => 1,
      '#max' => HarnessSettings::MAX_LIMIT,
      '#step' => 1,
      '#required' => TRUE,
    ];
    $form['harness']['task_limits']['max_tokens'] = [
      '#type' => 'number',
      '#title' => $this->t('每个任务最多已报告 Token'),
      '#description' => $this->t('按模型实际报告的输入与输出 Token 累计。达到上限后停止下一次调用，单次响应可能超过此值。对应 CHAT_TASK_MAX_TOKENS。'),
      '#default_value' => $harness['task_limits']['max_tokens'],
      '#min' => 1,
      '#max' => HarnessSettings::MAX_LIMIT,
      '#step' => 1,
      '#required' => TRUE,
    ];
    $service = $config->get('harness.service') ?? [];
    $form['harness']['service'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('服务端调用'),
      '#description' => $this->t('图片任务可在生成前请求对话服务改写提示词。填写服务地址（仅协议、主机和端口），留空则不改写。在 settings.php 的 xinshi_ai_service.secrets 配置独立服务密钥，并与 Node 的 AI_SERVICE_SECRET 保持一致。此功能不要求开启计量。'),
      'url' => [
        '#type' => 'url', '#title' => $this->t('对话服务地址'), '#maxlength' => 2048,
        '#default_value' => $service['url'] ?? '',
      ],
      'producer_id' => [
        '#type' => 'textfield', '#title' => $this->t('生产者 ID'), '#maxlength' => 64,
        '#default_value' => $service['producer_id'] ?? 'chat-node',
        '#description' => $this->t('与对话服务的 AI_SERVICE_PRODUCER_ID 及服务密钥映射键一致，默认 chat-node。'),
      ],
    ];

    $form['image'] = [
      '#type' => 'details',
      '#title' => $this->t('图片任务'),
      '#open' => TRUE,
    ];
    $form['image']['default_n'] = [
      '#type' => 'number',
      '#title' => $this->t('默认生成张数'),
      '#description' => $this->t('单次任务默认生成张数;具体上界由模型的 max_n 决定。'),
      '#min' => 1,
      '#default_value' => $config->get('image.default_n'),
    ];

    $form['queue'] = [
      '#type' => 'details',
      '#title' => $this->t('队列消费'),
      '#open' => TRUE,
    ];
    $form['queue']['worker'] = [
      '#type' => 'select',
      '#title' => $this->t('消费方式'),
      '#options' => [
        'daemon' => $this->t('常驻消费者(supervisor 跑 drush queue:run)— 生产推荐'),
        'cron' => $this->t('Cron + 响应后即时消费(在 PHP-FPM 进程内处理)'),
      ],
      '#description' => $this->t('daemon:不在 web 进程处理,需配置 supervisor 常驻消费者,以 web 用户身份运行。cron:创建任务的请求结束后在 PHP-FPM 进程内即时消费(开发便利,但会占住 worker,且该进程用户须能写 media 目录)。'),
      '#default_value' => $config->get('queue.worker') ?: 'daemon',
    ];

    $form['event_ticket'] = [
      '#type' => 'details',
      '#title' => $this->t('事件票据(SSE 鉴权)'),
    ];    $form['event_ticket']['ttl'] = [
      '#type' => 'number',
      '#title' => $this->t('TTL(秒)'),
      '#min' => 1,
      '#default_value' => $config->get('event_ticket.ttl'),
    ];

    $form['sse'] = [
      '#type' => 'details',
      '#title' => $this->t('SSE'),
    ];
    $form['sse']['heartbeat_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('心跳间隔(秒)'),
      '#min' => 1,
      '#default_value' => $config->get('sse.heartbeat_seconds'),
    ];
    $form['sse']['max_lifetime_seconds'] = [
      '#type' => 'number',
      '#title' => $this->t('最大存活(秒)'),
      '#min' => 1,
      '#default_value' => $config->get('sse.max_lifetime_seconds'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $gatewayUrl = trim((string) $form_state->getValue('base_url', ''));
    $aliyunUrl = trim((string) $form_state->getValue(['aliyun', 'base_url'], ''));
    foreach (['base_url' => $gatewayUrl, 'aliyun][base_url' => $aliyunUrl] as $name => $url) {
      if ($url !== '' && !SupplierConfiguration::validUrl($url)) {
        $form_state->setErrorByName($name, $this->t('填写 HTTPS 地址，不能包含用户信息、查询参数或片段，本地开发地址允许 HTTP。'));
      }
    }
    foreach (['api_key' => $form_state->getValue('api_key', ''),
      'aliyun][api_key' => $form_state->getValue(['aliyun', 'api_key'], '')] as $name => $key) {
      if (!is_string($key) || strlen($key) > 4096 || preg_match('/[\s\x00-\x1f\x7f]/', $key)) {
        $form_state->setErrorByName($name, $this->t('密钥不能包含空白字符，长度不能超过 4096 字节。'));
      }
    }
    if ($aliyunUrl === '' && ($form_state->getValue(['aliyun', 'api_key']) ||
      !$form_state->getValue(['aliyun', 'clear_key']) && $this->suppliers->hasKey('aliyun'))) {
      $form_state->setErrorByName('aliyun][base_url', $this->t('配置阿里云密钥时，必须填写对话 API 地址。'));
    }
    if ($form_state->getValue(['harness', 'mcp', 'product_documents', 'enabled']) &&
        !array_filter($form_state->getValue(['harness', 'mcp', 'product_documents', 'content_types'], []))) {
      $form_state->setErrorByName('harness][mcp][product_documents][content_types', $this->t('启用产品资料读取时，请至少选择一种资料内容类型。'));
    }
    $figma = $form_state->getValue(['harness', 'mcp', 'figma'], []);
    if (!empty($figma['enabled'])) {
      if (trim((string) ($figma['client_id'] ?? '')) === '') {
        $form_state->setErrorByName('harness][mcp][figma][client_id', $this->t('启用 Figma 时需要填写 Client ID。'));
      }
      if (trim((string) ($figma['client_secret'] ?? '')) === '' &&
          (!empty($figma['clear_secret']) || !\Drupal::service('xinshi_ai.figma_vault')->has('client_secret'))) {
        $form_state->setErrorByName('harness][mcp][figma][client_secret', $this->t('启用 Figma 时需要保存 Client Secret。'));
      }
    }
    $uri = trim((string) ($figma['redirect_uri'] ?? ''));
    if (($uri !== '' || !empty($figma['enabled'])) && !FigmaConnector::origin($uri)) {
      $form_state->setErrorByName('harness][mcp][figma][redirect_uri', $this->t('填写有效的 HTTPS 信使回调地址，路径必须是 /chat/figma/callback，不能包含用户信息、查询参数或片段。'));
    }
    $searchKey = trim((string) $form_state->getValue(['harness', 'web_search', 'api_key'], ''));
    if (strlen($searchKey) > 4096 || preg_match('/\s/', $searchKey)) {
      $form_state->setErrorByName('harness][web_search][api_key', $this->t('DeepSeek API Key 不能含空白字符，且不能超过 4096 字节。'));
    }
    $service = $form_state->getValue(['harness', 'service'], []);
    $serviceUrl = trim((string) ($service['url'] ?? ''));
    if ($serviceUrl !== '' && !PromptRewriteClient::isServiceUrl($serviceUrl)) {
      $form_state->setErrorByName('harness][service][url', $this->t('对话服务地址只能是 http(s) 源地址，不含路径、查询参数、用户信息或片段。'));
    }
    if (!preg_match(ServiceIdentity::PRODUCER_PATTERN, trim((string) ($service['producer_id'] ?? '')))) {
      $form_state->setErrorByName('harness][service][producer_id', $this->t('生产者 ID 只能包含字母、数字、点、下划线和连字符。'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    foreach (['xinshi' => [$form_state->getValue('api_key', ''), $form_state->getValue('clear_gateway_key')],
      'aliyun' => [$form_state->getValue(['aliyun', 'api_key'], ''), $form_state->getValue(['aliyun', 'clear_key'])]] as $platform => [$key, $clear]) {
      if ($key !== '') {
        $this->supplierKeys->set($key, $platform);
      }
      elseif ($clear) {
        $this->supplierKeys->delete($platform);
      }
    }
    $search = $form_state->getValue(['harness', 'web_search']);
    if (is_array($search)) {
      $credentials = \Drupal::service('xinshi_ai.web_search_credentials');
      $key = trim((string) ($search['api_key'] ?? ''));
      if ($key !== '') {
        $credentials->set($key);
      }
      elseif (!empty($search['clear_key'])) {
        $credentials->delete();
      }
    }
    $enabled = [];
    foreach (HarnessSettings::TOOL_GROUPS as $id => $group) {
      $values = $form_state->getValue(['harness', 'tools', 'enabled', $id], []);
      foreach (array_keys($group['tools']) as $name) {
        if (($values[$name] ?? NULL) === $name) {
          $enabled[] = $name;
        }
      }
    }
    $types = \Drupal::service('xinshi_ai.product_documents')->availableContentTypes();
    $selected = $form_state->getValue(['harness', 'mcp', 'product_documents', 'content_types'], []);
    $documentTypes = array_values(array_filter(array_keys($types), static fn($type) => ($selected[$type] ?? NULL) === $type));
    $figma = $form_state->getValue(['harness', 'mcp', 'figma']);
    if (is_array($figma)) {
      $vault = \Drupal::service('xinshi_ai.figma_vault');
      $secret = trim((string) ($figma['client_secret'] ?? ''));
      if ($secret !== '') {
        $vault->set('client_secret', ['value' => $secret]);
      }
      elseif (!empty($figma['clear_secret'])) {
        $vault->delete('client_secret');
      }
      $this->config(self::CONFIG_NAME)->set('harness.mcp.figma', [
        'enabled' => (bool) ($figma['enabled'] ?? FALSE),
        'client_id' => trim((string) ($figma['client_id'] ?? '')),
        'redirect_uri' => trim((string) ($figma['redirect_uri'] ?? '')),
      ]);
    }
    $this->config(self::CONFIG_NAME)
      ->set('speech.enabled', (bool) $form_state->getValue(['harness', 'web_search', 'speech_enabled']))
      ->set('gateway.base_url', $form_state->getValue('base_url'))
      ->clear('gateway.api_key')
      ->set('suppliers.aliyun.base_url', rtrim(trim((string) $form_state->getValue(['aliyun', 'base_url'], '')), '/'))
      ->set('gateway.request_timeout', (int) $form_state->getValue('request_timeout'))
      ->set('image.default_n', (int) $form_state->getValue('default_n'))
      ->set('queue.worker', $form_state->getValue('worker'))
      ->set('event_ticket.ttl', (int) $form_state->getValue('ttl'))
      ->set('sse.heartbeat_seconds', (int) $form_state->getValue('heartbeat_seconds'))
      ->set('sse.max_lifetime_seconds', (int) $form_state->getValue('max_lifetime_seconds'))
      ->set('harness.tools.pages_enabled', (bool) $form_state->getValue(['harness', 'tools', 'pages_enabled']))
      ->set('harness.tools.disabled', array_values(array_diff(HarnessSettings::toolNames(), $enabled)))
      ->set('harness.mcp.product_documents.enabled', (bool) $form_state->getValue(['harness', 'mcp', 'product_documents', 'enabled']))
      ->set('harness.mcp.product_documents.content_types', $documentTypes)
      ->set('harness.task_limits.max_model_calls', (int) $form_state->getValue(['harness', 'task_limits', 'max_model_calls']))
      ->set('harness.task_limits.max_tokens', (int) $form_state->getValue(['harness', 'task_limits', 'max_tokens']))
      ->set('harness.service.url', rtrim(trim((string) $form_state->getValue(['harness', 'service', 'url'])), '/'))
      // The producer ID never stores empty: the client falls back to chat-node as well.
      ->set('harness.service.producer_id', trim((string) $form_state->getValue(['harness', 'service', 'producer_id'])) ?: 'chat-node')
      ->save();

    parent::submitForm($form, $form_state);
  }

}
