<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\xinshi_ai\Service\ModelDefaultRules;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Manage the same default roles as the Builder model management page. */
final class ModelDefaultsForm extends FormBase {

  /** Construct the form with the site's shared model registry. */
  public function __construct(
    private readonly ModelRegistryServiceInterface $registry,
  ) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_ai.model_registry'));
  }

  /** {@inheritdoc} */
  public function getFormId(): string {
    return 'xinshi_ai_model_defaults_form';
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $registry = $this->registry->getRegistry();
    $titles = [
      'chat' => $this->t('对话'),
      'critic' => $this->t('Plan 评审'),
      'classifier' => $this->t('意图分类'),
      'image' => $this->t('生图'),
      'image-edit' => $this->t('修图'),
    ];
    $form['description'] = [
      '#markup' => $this->t('选择各用途的默认模型，与 Builder 模型管理共用设置。选择“未设置”并保存可清除对应默认项。'),
    ];
    foreach ($titles as $mode => $title) {
      $options = [];
      foreach ($registry['models'] as $model) {
        $id = $model['id'];
        if (ModelDefaultRules::validate($this->registry, $mode, $id) === NULL) {
          $options[$id] = ($model['label'] ?? $id) . ' (' . $id . ')';
        }
      }
      $current = $registry['defaults'][$mode] ?? '';
      // Keep invalid saved selections visible until the administrator explicitly resolves them.
      if ($current !== '' && !isset($options[$current])) {
        $options[$current] = $this->t('@id（当前配置不可用，请重新选择或清除）', ['@id' => $current]);
      }
      $form[$mode] = [
        '#type' => 'select',
        '#title' => $title,
        '#options' => $options,
        '#empty_option' => $this->t('未设置'),
        '#empty_value' => '',
        '#default_value' => $current,
      ];
    }
    $form['critic']['#description'] = $this->t('仅支持已启用的信使对话模型。清除后，未指定评审模型的 Plan 请求将无法完成评审。');
    $form['classifier']['#description'] = $this->t('仅支持已启用的信使对话模型。清除后，信使普通对话将跳过意图分类。');
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('保存默认模型'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (ModelRegistryServiceInterface::DEFAULT_MODES as $mode) {
      $error = ModelDefaultRules::validate($this->registry, $mode, $form_state->getValue($mode));
      if ($error !== NULL) {
        $form_state->setErrorByName($mode, $this->t('所选模型不可用或不支持此用途，请重新选择或清除。'));
      }
    }
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $defaults = [];
    foreach (ModelRegistryServiceInterface::DEFAULT_MODES as $mode) {
      $id = $form_state->getValue($mode);
      if (is_string($id) && $id !== '') {
        $defaults[$mode] = $id;
      }
    }
    $this->registry->saveDefaults($defaults);
    $this->messenger()->addStatus($this->t('默认模型已保存。'));
    $form_state->setRedirect('xinshi_ai.models.defaults');
  }

}
