<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge_sync\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Url;
use Drupal\user\RoleInterface;
use Drupal\xinshi_knowledge_sync\Service\SourceAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Edits one source without overwriting other sources or changing source identities. */
final class SourceForm extends ConfigFormBase {

  private const CONFIG = 'xinshi_knowledge_sync.settings';

  /** Keeps injected services serializable by FormBase across AJAX requests. */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected EntityTypeManagerInterface $entities,
    protected Connection $database,
    protected LockBackendInterface $lock,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('config.factory'), $container->get('config.typed'),
      $container->get('entity_type.manager'), $container->get('database'), $container->get('lock'));
  }

  /** {@inheritdoc} */
  protected function getEditableConfigNames(): array {
    return [self::CONFIG];
  }

  /** {@inheritdoc} */
  public function getFormId(): string {
    return 'xinshi_knowledge_sync_source';
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $source_id = NULL): array {
    if (!$form_state->has('original')) {
      $original = $this->find($this->config(self::CONFIG)->get('sources') ?? [], $source_id);
      if ($source_id !== NULL && $original === NULL) {
        throw new NotFoundHttpException();
      }
      $source = $original ?? ['id' => 'xinshi-docs', 'enabled' => FALSE, 'default_policy' => 'readers',
        'policies' => [['id' => 'readers', 'all_readers' => FALSE,
          'roles' => [RoleInterface::AUTHENTICATED_ID], 'users' => []]],
        'rules' => [
          ['prefix' => 'config/builder/', 'policy' => 'readers'],
          ['prefix' => 'pro/ai/', 'policy' => 'readers'],
        ]];
      $source += ['enabled' => FALSE, 'default_policy' => '', 'policies' => [], 'rules' => []];
      $source['policies'] = array_map(fn($policy) => $policy + ['all_readers' => FALSE, 'roles' => [], 'users' => []], $source['policies']);
      $form_state->set('original', ['id' => $source_id, 'value' => $original]);
      $form_state->set('source_defaults', $source);
      foreach (['policies', 'rules'] as $section) {
        $form_state->set($section . '_rows', array_keys($source[$section]));
        $form_state->set($section . '_next', count($source[$section]));
      }
    }
    $source = $form_state->get('source_defaults');
    $input = $form_state->getUserInput()['settings'] ?? [];
    // Initial GET forms are not cached by core. Carry the opened version through ordinary POSTs.
    $form['source_fingerprint'] = ['#type' => 'hidden',
      '#default_value' => $this->fingerprint($form_state->get('original')['value'])];
    $roles = [];
    foreach ($this->entities->getStorage('user_role')->loadMultiple() as $role) {
      if ($role->id() !== RoleInterface::ANONYMOUS_ID) {
        $roles[$role->id()] = $role->label();
      }
    }
    $policyOptions = [];
    foreach ($form_state->get('policies_rows') as $index) {
      $persisted = $form_state->get('original')['id'] !== NULL && isset($source['policies'][$index]);
      $id = $persisted ? $source['policies'][$index]['id']
        : ($input['policies'][$index]['id'] ?? $source['policies'][$index]['id'] ?? '');
      if (is_string($id) && trim($id) !== '') {
        $policyOptions[trim($id)] = trim($id);
      }
    }
    $form['intro'] = ['#markup' => '<p>' . $this->t('来源标识须与文档导出配置一致。保存只更新来源与阅读范围，文档内容仍由同步任务导入。已有来源可停用，标识不能改名。') . '</p>'];
    $form['settings'] = ['#type' => 'container', '#tree' => TRUE,
      '#attributes' => ['id' => 'knowledge-sync-source-settings']];
    $settings = &$form['settings'];
    $settings['id'] = [
      '#type' => 'textfield', '#title' => $this->t('来源标识'), '#required' => TRUE,
      '#default_value' => $source['id'], '#maxlength' => 64,
      '#disabled' => $form_state->get('original')['id'] !== NULL,
      '#description' => $this->t('默认 xinshi-docs，客户文档可改为 customer-docs 等标识。使用小写字母、数字、下划线或连字符，以字母或数字开头，并与导出配置保持一致。'),
    ];
    $settings['enabled'] = ['#type' => 'checkbox', '#title' => $this->t('启用来源'),
      '#default_value' => $source['enabled'],
      '#description' => $this->t('停用后，同步任务拒绝导入该来源，普通读者也无法继续查询其文档。')];
    $settings['policies'] = ['#type' => 'details', '#title' => $this->t('阅读策略'), '#open' => TRUE];
    foreach ($form_state->get('policies_rows') as $index) {
      $policy = $source['policies'][$index] ?? ['id' => '', 'all_readers' => FALSE, 'roles' => [], 'users' => []];
      $row = [
        '#type' => 'fieldset', '#title' => $this->t('阅读策略 @number', ['@number' => $index + 1]),
        'id' => ['#type' => 'textfield', '#title' => $this->t('策略标识'), '#required' => TRUE,
          '#maxlength' => 64, '#default_value' => $policy['id'],
          '#disabled' => isset($source['policies'][$index]) && $form_state->get('original')['id'] !== NULL,
          '#description' => $this->t('使用小写字母、数字、下划线或连字符。新标识填完后，默认策略和目录规则的选项会更新。'),
          '#limit_validation_errors' => [],
          '#ajax' => ['callback' => '::refresh', 'wrapper' => 'knowledge-sync-source-settings', 'event' => 'change']],
        'all_readers' => ['#type' => 'checkbox', '#title' => $this->t('允许全部知识库读者'),
          '#default_value' => $policy['all_readers'],
          '#description' => $this->t('仍需知识库及节点查看权限，不代表匿名公开。未勾选时，仅允许下方选定的角色或账号；都不选则无人获得此策略的阅读权。')],
        'roles' => ['#type' => 'checkboxes', '#title' => $this->t('允许的角色'),
          '#options' => $roles, '#default_value' => $policy['roles']],
        'users' => ['#type' => 'entity_autocomplete', '#title' => $this->t('允许的账号'),
          '#target_type' => 'user', '#tags' => TRUE, '#selection_settings' => ['include_anonymous' => FALSE],
          '#default_value' => array_values($this->entities->getStorage('user')->loadMultiple($policy['users'])),
          '#description' => $this->t('输入用户名并选择账号，可添加多个账号。角色与账号任一匹配即可。')],
        'remove' => $this->rowButton('policies', $index, $this->t('移除策略 @number', ['@number' => $index + 1])),
      ];
      $settings['policies'][$index] = $row;
    }
    $settings['add_policy'] = $this->rowButton('policies', NULL, $this->t('添加阅读策略'));
    $settings['default_policy'] = [
      '#type' => 'select', '#title' => $this->t('默认阅读策略'), '#options' => $policyOptions,
      '#empty_option' => $this->t('- 请选择 -'), '#required' => TRUE,
      '#default_value' => $source['default_policy'],
      '#description' => $this->t('没有匹配目录规则的文档使用此策略。更改映射后，需要重新同步文档。'),
    ];
    $settings['rules'] = ['#type' => 'details', '#title' => $this->t('目录规则'), '#open' => TRUE,
      '#description' => $this->t('相对导出配置的文档根目录填写，以 / 结尾。默认规则对应 xinshi-docs/stories/ 下的 config/builder/ 和 pro/ai/，无需再加 stories/。根目录文档使用默认策略；客户文档可修改或移除这些规则。多个规则匹配时使用最长前缀，规则在下一次同步时生效。')];
    foreach ($form_state->get('rules_rows') as $index) {
      $rule = $source['rules'][$index] ?? ['prefix' => '', 'policy' => ''];
      $settings['rules'][$index] = [
        '#type' => 'fieldset', '#title' => $this->t('目录规则 @number', ['@number' => $index + 1]),
        'prefix' => ['#type' => 'textfield', '#title' => $this->t('目录前缀'), '#required' => TRUE,
          '#maxlength' => 512, '#default_value' => $rule['prefix'], '#description' => $this->t('例如 internal/ 或 manuals/operations/。')],
        'policy' => ['#type' => 'select', '#title' => $this->t('阅读策略'), '#required' => TRUE,
          '#options' => $policyOptions, '#empty_option' => $this->t('- 请选择 -'), '#default_value' => $rule['policy']],
        'remove' => $this->rowButton('rules', $index, $this->t('移除规则 @number', ['@number' => $index + 1])),
      ];
    }
    $settings['add_rule'] = $this->rowButton('rules', NULL, $this->t('添加目录规则'));
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('保存来源');
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('返回来源列表'),
      '#url' => Url::fromRoute('xinshi_knowledge_sync.settings')];
    return $form;
  }

  /** Updates repeatable form rows without saving configuration. */
  public function changeRows(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $section = $trigger['#section'];
    $rows = $form_state->get($section . '_rows');
    if ($trigger['#row_index'] === NULL) {
      $rows[] = $form_state->get($section . '_next');
      $form_state->set($section . '_next', $form_state->get($section . '_next') + 1);
    }
    else {
      $rows = array_values(array_diff($rows, [$trigger['#row_index']]));
    }
    $form_state->set($section . '_rows', $rows)->setRebuild();
  }

  /** Returns the rebuilt controls for AJAX changes. */
  public function refresh(array &$form, FormStateInterface $form_state): array {
    return $form['settings'];
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (isset($form_state->getTriggeringElement()['#ajax'])) {
      return;
    }
    parent::validateForm($form, $form_state);
    $value = $form_state->getValue('settings');
    $original = $form_state->get('original');
    $id = trim((string) ($value['id'] ?? ''));
    if (!$this->validId($id) || ($original['id'] !== NULL && $id !== $original['id'])) {
      $form_state->setErrorByName('settings][id', $this->t('来源标识格式无效，已有来源不能改名。'));
    }
    $sources = $this->config(self::CONFIG)->get('sources') ?? [];
    $current = $this->find($sources, $id);
    if ($original['id'] === NULL && $current !== NULL) {
      $form_state->setErrorByName('settings][id', $this->t('该来源标识已存在，请使用其他标识。'));
    }
    $fingerprint = $form_state->getValue('source_fingerprint');
    if (!is_string($fingerprint) || !hash_equals($this->fingerprint($current), $fingerprint)) {
      $form_state->setErrorByName('settings', $this->t('来源已被其他管理员修改，请重新加载后再编辑。'));
    }
    $policies = [];
    $ids = [];
    if (!$form_state->get('policies_rows')) {
      $form_state->setErrorByName('settings][policies', $this->t('请至少添加一项阅读策略。'));
    }
    foreach ($form_state->get('policies_rows') as $index) {
      $policy = $value['policies'][$index];
      $policyId = trim((string) $policy['id']);
      if (!$this->validId($policyId) || isset($ids[$policyId])) {
        $form_state->setErrorByName("settings][policies][$index][id", $this->t('策略标识格式无效或重复。'));
      }
      $ids[$policyId] = TRUE;
      $users = array_values(array_unique(array_map('intval', array_column($policy['users'] ?? [], 'target_id'))));
      $accounts = $this->entities->getStorage('user')->loadMultiple($users);
      foreach ($users as $uid) {
        if ($uid <= 0 || !isset($accounts[$uid]) || !$accounts[$uid]->isActive()) {
          $form_state->setErrorByName("settings][policies][$index][users", $this->t('请选择有效且未停用的账号。'));
        }
      }
      $policies[] = ['id' => $policyId, 'all_readers' => (bool) $policy['all_readers'],
        'roles' => array_values(array_filter($policy['roles'] ?? [])), 'users' => $users];
    }
    if (!isset($ids[$value['default_policy'] ?? ''])) {
      $form_state->setErrorByName('settings][default_policy', $this->t('请选择当前来源中有效的默认阅读策略。'));
    }
    $rules = [];
    $prefixes = [];
    foreach ($form_state->get('rules_rows') as $index) {
      $rule = $value['rules'][$index];
      $prefix = trim((string) $rule['prefix']);
      $parts = explode('/', substr($prefix, 0, -1));
      if ($prefix === '' || !str_ends_with($prefix, '/') || preg_match('/[\\\\\x00-\x1f\x7f]/', $prefix) ||
          array_intersect($parts, ['', '.', '..']) || isset($prefixes[$prefix])) {
        $form_state->setErrorByName("settings][rules][$index][prefix", $this->t('请输入不重复的相对目录前缀，以 / 结尾，且不包含空路径、. 或 ..。'));
      }
      $prefixes[$prefix] = TRUE;
      if (!isset($ids[$rule['policy'] ?? ''])) {
        $form_state->setErrorByName("settings][rules][$index][policy", $this->t('请选择当前来源中的阅读策略。'));
      }
      $rules[] = ['prefix' => $prefix, 'policy' => $rule['policy']];
    }
    if ($this->hasReferencedRemoval($current, $policies)) {
      $form_state->setErrorByName('settings][policies', $this->t('有文档仍关联已移除的策略。请先保留该策略，保存新的目录映射并完成同步，再移除它。'));
    }
    $form_state->set('normalized_source', ['id' => $id, 'enabled' => (bool) $value['enabled'],
      'default_policy' => $value['default_policy'], 'policies' => $policies, 'rules' => $rules]);
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Use the import lock so a form save cannot change policy mappings mid-import.
    if (!$this->lock->acquire('xinshi_knowledge_sync.import', 30)) {
      $this->messenger()->addError($this->t('同步或配置保存正在进行，请稍后重试。'));
      $form_state->setRebuild();
      return;
    }
    try {
      $this->configFactory()->reset(self::CONFIG);
      $settings = $this->config(self::CONFIG);
      $sources = $settings->get('sources') ?? [];
      $source = $form_state->get('normalized_source');
      $current = $this->find($sources, $source['id']);
      if (!hash_equals($this->fingerprint($current), $form_state->getValue('source_fingerprint'))) {
        $this->messenger()->addError($this->t('来源已被其他管理员修改，请重新加载后再编辑。'));
        $form_state->setRebuild();
        return;
      }
      // An import may have added references after form validation, before acquiring this lock.
      if ($this->hasReferencedRemoval($current, $source['policies'])) {
        $this->messenger()->addError($this->t('同步文档仍在使用已移除的策略，请重新加载后保留该策略。'));
        $form_state->setRebuild();
        return;
      }
      $found = FALSE;
      foreach ($sources as &$item) {
        if ($item['id'] === $source['id']) {
          $item = $source;
          $found = TRUE;
          break;
        }
      }
      unset($item);
      if (!$found) {
        $sources[] = $source;
      }
      $settings->set('sources', $sources)->save();
      $this->messenger()->addStatus($this->t('文档来源已保存。目录映射变更将在下一次同步时应用。'));
      $form_state->setRedirect('xinshi_knowledge_sync.settings');
    }
    finally {
      $this->lock->release('xinshi_knowledge_sync.import');
    }
  }

  private function rowButton(string $section, ?int $index, mixed $title): array {
    return ['#type' => 'submit', '#value' => $title, '#name' => $section . '_' . ($index ?? 'add'),
      '#section' => $section, '#row_index' => $index, '#submit' => ['::changeRows'],
      '#limit_validation_errors' => [], '#ajax' => ['callback' => '::refresh', 'wrapper' => 'knowledge-sync-source-settings']];
  }

  private function find(array $sources, ?string $id): ?array {
    foreach ($sources as $source) {
      if ($source['id'] === $id) {
        return $source;
      }
    }
    return NULL;
  }

  private function validId(string $id): bool {
    return (bool) preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $id);
  }

  /** Detects concurrent source changes without persisting form state on a GET request. */
  private function fingerprint(?array $source): string {
    return hash('sha256', serialize($source));
  }

  /** Keeps ledger policies resolvable, including documents that have been unpublished. */
  private function hasReferencedRemoval(?array $original, array $policies): bool {
    $removed = array_diff(array_column($original['policies'] ?? [], 'id'), array_column($policies, 'id'));
    return $removed && (bool) $this->database->select(SourceAccess::TABLE, 'd')->fields('d', ['nid'])
      ->condition('source', $original['id'])->condition('policy', array_values($removed), 'IN')
      ->range(0, 1)->execute()->fetchField();
  }

}
