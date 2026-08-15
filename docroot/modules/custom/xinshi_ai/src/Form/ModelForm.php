<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 新增 / 编辑模型注册条目。
 *
 * 平台(xinshi/custom)是固定基建,这里只作为下拉选项,不在此增删。
 */
final class ModelForm extends FormBase {

  /** 受支持的能力枚举(与 ImageGenerationTask / 前端契约一致)。 */
  private const CAPABILITIES = [
    'chat' => '对话 (chat)',
    'reasoning' => '推理 (reasoning)',
    'image' => '文生图 (image)',
    'image-edit' => '图生图 / 编辑 (image-edit)',
  ];

  public function __construct(
    private readonly ModelRegistryServiceInterface $registry,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_ai.model_registry'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'xinshi_ai_model_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $id = NULL): array {
    $model = $id !== NULL ? $this->registry->getModel($id) : NULL;
    // getModel() 会过滤掉 enabled=false 的平台与模型;编辑场景下仍需能取到。
    if ($id !== NULL && $model === NULL) {
      foreach ($this->registry->getRegistry()['models'] as $candidate) {
        if (($candidate['id'] ?? NULL) === $id) {
          $model = $candidate;
          break;
        }
      }
    }
    $isEdit = $model !== NULL;
    $form_state->set('original_id', $isEdit ? (string) $model['id'] : '');

    $platformOptions = [];
    foreach ($this->registry->getPlatforms() as $platform) {
      $platformOptions[$platform['id']] = $platform['label'] ?? $platform['id'];
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('显示名称'),
      '#default_value' => $isEdit ? ($model['label'] ?? '') : '',
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('模型 ID'),
      '#description' => $this->t('上游真实模型 ID(调用网关 / 厂商时使用),如 gpt-image-2、qwen-image-max。'),
      '#default_value' => $isEdit ? $model['id'] : '',
      '#required' => TRUE,
      '#maxlength' => 128,
      '#machine_name' => [
        'exists' => [$this, 'idExists'],
        'label' => $this->t('模型 ID'),
        'replace_pattern' => '[^a-z0-9_.\-]+',
        'replace' => '-',
        'source' => ['label'],
      ],
    ];
    $form['platform'] = [
      '#type' => 'select',
      '#title' => $this->t('平台'),
      '#options' => $platformOptions,
      '#default_value' => $isEdit ? ($model['platform'] ?? '') : 'xinshi',
      '#required' => TRUE,
    ];
    $form['capabilities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('能力'),
      '#options' => self::CAPABILITIES,
      '#default_value' => $isEdit ? ($model['capabilities'] ?? []) : [],
      '#required' => TRUE,
    ];
    $form['max_n'] = [
      '#type' => 'number',
      '#title' => $this->t('最大生成张数'),
      '#description' => $this->t('仅图像模型需要;留空表示不限制(或非图像模型)。'),
      '#min' => 1,
      '#default_value' => $isEdit ? ($model['max_n'] ?? NULL) : NULL,
    ];
    $form['sizes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('支持的尺寸'),
      '#description' => $this->t('每行一个,如 1024x1024。仅图像模型需要。'),
      '#default_value' => $isEdit ? implode("\n", $model['sizes'] ?? []) : '',
      '#rows' => 4,
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('启用'),
      '#description' => $this->t('禁用后不对前台暴露,任务提交校验也会拒绝;记录保留,可随时重新启用。'),
      '#default_value' => $isEdit ? (bool) ($model['enabled'] ?? TRUE) : TRUE,
    ];
    $form['deprecated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('已弃用'),
      '#description' => $this->t('弃用后仍保留记录,但不建议前端继续使用。'),
      '#default_value' => $isEdit ? !empty($model['deprecated']) : FALSE,
    ];
    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('备注'),
      '#default_value' => $isEdit ? ($model['notes'] ?? '') : '',
      '#rows' => 2,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('保存'),
        '#button_type' => 'primary',
      ],
      'cancel' => [
        '#type' => 'link',
        '#title' => $this->t('取消'),
        '#url' => Url::fromRoute('xinshi_ai.models.overview'),
        '#attributes' => ['class' => ['button']],
      ],
    ];

    return $form;
  }

  /**
   * machine_name 唯一性校验:改 id 时不能撞已有模型。
   */
  public function idExists(string $id, array $element, FormStateInterface $form_state): bool {
    if ($id === $form_state->get('original_id')) {
      return FALSE;
    }
    foreach ($this->registry->getRegistry()['models'] as $model) {
      if (($model['id'] ?? NULL) === $id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $capabilities = array_values(array_filter($form_state->getValue('capabilities')));
    $sizes = array_values(array_filter(array_map(
      'trim',
      preg_split('/\r\n|\r|\n/', (string) $form_state->getValue('sizes')) ?: [],
    )));

    $model = [
      'id' => (string) $form_state->getValue('id'),
      'label' => (string) $form_state->getValue('label'),
      'platform' => (string) $form_state->getValue('platform'),
      'capabilities' => $capabilities,
    ];
    $maxN = $form_state->getValue('max_n');
    if ($maxN !== '' && $maxN !== NULL) {
      $model['max_n'] = (int) $maxN;
    }
    if ($sizes) {
      $model['sizes'] = $sizes;
    }
    if ($form_state->getValue('deprecated')) {
      $model['deprecated'] = TRUE;
    }
    // enabled 缺省为启用;仅显式禁用时落库(与种子数据保持最小键集)。
    if (!$form_state->getValue('enabled')) {
      $model['enabled'] = FALSE;
    }
    $notes = trim((string) $form_state->getValue('notes'));
    if ($notes !== '') {
      $model['notes'] = $notes;
    }

    $this->registry->saveModel($model, (string) $form_state->get('original_id'));
    $this->messenger()->addStatus($this->t('模型 %label 已保存。', ['%label' => $model['label']]));
    $form_state->setRedirect('xinshi_ai.models.overview');
  }

}
