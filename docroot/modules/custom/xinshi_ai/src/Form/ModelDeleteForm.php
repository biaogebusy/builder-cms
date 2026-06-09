<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 删除模型注册条目的确认表单。
 */
final class ModelDeleteForm extends ConfirmFormBase {

  private string $modelId = '';

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
    return 'xinshi_ai_model_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $id = NULL): array {
    $this->modelId = (string) $id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): \Stringable|string {
    return $this->t('确定删除模型 %id 吗?', ['%id' => $this->modelId]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): \Stringable|string {
    return $this->t('删除后该模型将不再出现在注册中心,使用它的新任务会校验失败。此操作不可撤销。');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('xinshi_ai.models.overview');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->registry->deleteModel($this->modelId);
    $this->messenger()->addStatus($this->t('模型 %id 已删除。', ['%id' => $this->modelId]));
    $form_state->setRedirect('xinshi_ai.models.overview');
  }

}
