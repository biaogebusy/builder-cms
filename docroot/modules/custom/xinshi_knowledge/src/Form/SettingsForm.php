<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_knowledge\Service\AttachmentParser;

final class SettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['xinshi_knowledge.settings'];
  }

  public function getFormId(): string {
    return 'xinshi_knowledge_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = ['#markup' => '<p>' . $this->t('上传到知识库的文件由后台队列解析。支持 Word（doc、docx）、Excel（xls、xlsx）、文字 PDF 和 TXT；单文件最多 20 MB。不执行文件中的脚本、宏或公式。扫描 PDF 需要先进行 OCR。') . '</p>'];
    if (!Settings::get('file_private_path')) {
      $this->messenger()->addWarning($this->t('尚未配置 Drupal 私有文件目录，知识库附件暂时无法上传。请先完成服务器的私有文件设置。'));
    }
    $form['tika_url'] = [
      '#type' => 'url', '#title' => $this->t('内部 Apache Tika 服务地址'),
      '#default_value' => $this->config('xinshi_knowledge.settings')->get('tika_url'),
      '#description' => $this->t('填写由本站维护的解析服务根地址。留空时仍可解析 TXT；其他格式会显示“未配置解析服务”。地址不交给 AI，也不能由文档指定。'),
      '#maxlength' => 2048,
    ];
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $url = trim((string) $form_state->getValue('tika_url'));
    if ($url !== '' && !AttachmentParser::validServerUrl($url)) {
      $form_state->setErrorByName('tika_url', $this->t('请填写 http 或 https 服务地址，不含用户名、密码、查询参数或片段。'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('xinshi_knowledge.settings')->set('tika_url', rtrim(trim((string) $form_state->getValue('tika_url')), '/'))->save();
    parent::submitForm($form, $form_state);
  }

}
