<?php


namespace Drupal\xinshi_api\Controller;


use Drupal\block_content\Entity\BlockContent;
use Drupal\Component\Serialization\Json;
use Drupal\content_translation\ContentTranslationManager;
use Drupal\Core\Language\LanguageInterface;
use Drupal\node\Entity\Node;
use Drupal\panels\Plugin\DisplayVariant\PanelsDisplayVariant;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\panels_ipe\Controller\PanelsIPEPageController as BasePanelsIPEPageController;

/**
 * Class PanelsIPEPageController
 * @package Drupal\xinshi_api\Controller
 */
class PanelsIPEPageController extends BasePanelsIPEPageController {

  private $message;

  /**
   * @return string
   */
  public function getMessage() {
    return $this->message;
  }

  /**
   * @param string $message
   */
  public function setMessage($message): void {
    $this->message = $message;
  }

  /**
   * @return JsonResponse
   */
  public function landingPageBuilder() {
    $data = [];
    $status = FALSE;
    try {
      if ($json = $this->getRequest()) {
        $entity = $this->addLandingPage($json['title']);
        $this->saveLayout($entity, $this->getBlockContent($json['body']));
        $data['data'] = [
          'nid' => $entity->id(),
          'url' => $entity->toUrl()->toString(),
        ];
        $status = TRUE;
        $this->setMessage($this->t('Create landing page @name successful.', ['@name' => $entity->label()]));
      }
    } catch (\Exception $exception) {
      $this->setMessage($exception->getMessage());
    }
    $data['status'] = $status;
    $data['message'] = $this->getMessage();
    return new JsonResponse($data);
  }

  /**
   * Return request content
   * @param bool $validate
   * @return array
   */
  private function getRequest($validate = TRUE) {
    $content = \Drupal::request()->getContent();
    $json = json_decode($content, 1);
    if (empty($validate)) {
      return is_array($json) ? $json : [];
    }
    if (!is_array($json)) {
      $this->setMessage($this->t('Invalid parameter'));
      return [];
    }
    if (!($json['title'] ?? FALSE)) {
      $this->setMessage($this->t('Missing title'));
      return [];
    }
    if (!($json['body'] ?? FALSE)) {
      $this->setMessage($this->t('Missing body'));
      return [];
    }
    return $json;
  }

  /**
   * 添加着陆页
   * @param $title
   * @return Node
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function addLandingPage($title) {
    $entity = Node::create([
      'type' => 'landing_page',
      'title' => $title,
      'status' => 1,
      'moderation_state' => 'published',
    ]);
    $entity->save();
    return $entity;
  }

  /**
   * Create custom block content.
   * @param $data
   * @param false $rebuild
   * @param null $langcode
   * @return array
   */
  private function getBlockContent($data, $rebuild = FALSE, $langcode = NULL) {
    $langcode = $langcode ?? $this->currentLanguageId();
    $list = [];
    $number = $this->getNumber();
    foreach ($data as $row) {
      $uuid = $row['uuid'] ?? '';
      $body = $row['attributes']['body'] ?? '';
      $blocks = $uuid ? $this->entityTypeManager->getStorage('block_content')->loadByProperties(['uuid' => $uuid]) : FALSE;
      $block = $blocks ? reset($blocks) : FALSE;
      if ($block && $block->bundle() != 'json') {
        $block = FALSE;
      }
      if ((empty($block) || $rebuild) && $body) {
        $number++;
        $block = BlockContent::create([
          'type' => 'json',
          'info' => "Json {$number}",
          'body' => [
            'value' => $body,
            'format' => 'json',
          ],
          'langcode' => [
            'value' => $langcode,
          ],
        ]);
      }

      if ($block) {
        $block = $this->addBlockTranslation($block, $langcode);
        $block->set('body', [
          [
            'value' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'format' => 'json',
          ],
        ]);
        $block->save();
        $list[] = $block;
      }
    }
    return $list;
  }

  /**
   * Save layout.
   * @param Node $entity
   * @param array $blocks
   * @param false $add_translations
   */
  private function saveLayout(Node &$entity, array $blocks, $add_translations = FALSE) {
    $nid = $entity->id();
    $panels_storage_id = "node:{$nid}:full";

    /** @var PanelsDisplayVariant $panels_display */
    $panels_display = $this->loadPanelsDisplay('panelizer_field', $panels_storage_id);
    $regions = $panels_display->getLayout()->getPluginDefinition()->get('regions');
    $panels_display->setConfiguration([
      "storage_type" => "panelizer_field",
      "storage_id" => "node:{$nid}:full",
      "page_title" => "[node:title]",
      "layout" => "layout_onecol",
      "label" => $this->t("Default"),
      "pattern" => "panelizer",
      "builder" => "ipe",
    ]);
    $region = array_keys($regions)[0];
    $weight = 1;
    /** @var BlockContent $block */
    foreach ($blocks as $block) {
      $panels_display->addBlock([
        'id' => 'block_content:' . $block->uuid(),
        'label' => $block->label(),
        'label_display' => 0,
        'region' => $region,
        'weight' => $weight,
        'vid' => $block->getRevisionId(),
      ]);
      $weight++;
    }

    if ($add_translations) {
      $panelizer = $entity->get('panelizer')->getValue();
      $panelizer[0]['panels_display'] = $panels_display->getConfiguration();
      $entity->set('panelizer', $panelizer);
    } else {
      \Drupal::service('tempstore.shared')->get('panels_ipe')->delete($panels_display->getTempStoreId());
      \Drupal::service('panels.storage_manager')->save($panels_display);
    }
  }

  private function getNumber() {
    $entities = $this->entityTypeManager()->getStorage('block_content')->loadByProperties(['type' => 'json']);
    return count($entities);
  }

  /**
   * @param Node $node
   * @return JsonResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function landingPageCanonical(Node $node) {
    $data = [];
    $block_json = [];
    if ($node->bundle() == 'landing_page') {
      foreach ($this->getPanelBlocks($node) as $block) {
        if ($block->bundle() !== 'json') {
          $this->setMessage('This page contains non Json blocks and cannot be edited.');
          break;
        } else {
          $block_json[] = [
            'uuid' => $block->uuid(),
            'id' => $block->id(),
            'type' => $block->bundle(),
            'langcode' => $block->language()->getId(),
            'attributes' => [
              'body' => Json::decode(htmlspecialchars_decode($block->get('body')->value))??Json::decode($block->get('body')->value),
            ],
          ];
        }
      }
    } else {
      $this->setMessage('Invalid content type.');
    }
    $data['status'] = empty($this->getMessage());
    $data['message'] = $this->getMessage() ?? '';
    if ($data['status']) {
      $title = "[node:title] | [site:name]";
      $data['title'] = \Drupal::token()->replace($title, ['node' => $node]);
      $data['uuid'] = $node->uuid();
      $data['nid'] = $node->id();
      $data['vid'] = $node->getRevisionId();
      $data['changed'] = $node->getChangedTime();
      $data['langcode'] = $node->language()->getId();
      $data['label'] = $node->label();
      $data['body'] = $block_json;
    }
    return new JsonResponse($data);
  }

  /**
   * 更新着陆页
   * @param Node $node
   * @return JsonResponse
   */
  public function landingPageUpdate(Node $node) {
    $data = [];
    $status = FALSE;
    try {
      if ($node->bundle() == 'landing_page' && $this->entityValidate($node) && $json = $this->getRequest()) {

        if (!empty($json['title']) && $node->label() !== $json['title']) {
          $node->set('title', $json['title']);
          $node->save();
        }
        $this->saveLayout($node, $this->getBlockContent($json['body']));
        $data['data'] = [
          'nid' => $node->id(),
          'url' => $node->toUrl()->toString(),
        ];
        $this->setMessage($this->t('Update landing page @name successful.', ['@name' => $node->label()]));
        $status = TRUE;
      } elseif (empty($this->getMessage())) {
        $this->setMessage($this->t('Invalid parameter'));
      }
    } catch (\Exception $exception) {
      $this->setMessage($exception->getMessage());
    }

    $data['status'] = $status;
    $data['message'] = $this->getMessage() ?? '';
    return new JsonResponse($data);
  }

  private function entityValidate(Node $node) {
    $request = $this->getRequest();
    $vid = $request['vid'] ?? FALSE;
    if (empty($vid)) {
      $this->setMessage($this->t('Invalid parameter'));
      return FALSE;
    }
    if ($vid == $node->getRevisionId()) {
      return TRUE;
    }
    $revision = $this->entityTypeManager()->getStorage('node')->loadRevision($vid);
    if (empty($revision) || $node->id() != $revision->id()) {
      $this->setMessage($this->t('Invalid revision'));
      return FALSE;
    }
    if ($revision->hasTranslation($this->currentLanguageId())) {
      $revision = $revision->getTranslation($this->currentLanguageId());
    }
    if (json_encode($node->get('panelizer')->getValue()[0]['panels_display'] ?? '') == json_encode($revision->get('panelizer')->getValue()[0]['panels_display'] ?? '')) {
      return TRUE;
    } else {
      $this->setMessage($this->t('The content has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved.'));
      return FALSE;
    }
  }


  /**
   * 获取区块
   * @param Node $node
   * @return array
   */
  private function getPanelBlocks(Node $node) {
    $blocks = [];
    $panelizer = $node->get('panelizer')->getValue();
    foreach ($panelizer[0]['panels_display']['blocks'] ?? [] as $block_config) {
      if ($block_config['provider'] == 'block_content') {
        $entities = $this->entityTypeManager()->getStorage('block_content')->loadByProperties([
          'uuid' => explode(':', $block_config['id'])[1]]);
        if (empty($entities)) {
          continue;
        }
        $block = reset($entities);
        if ($block->hasTranslation($this->currentLanguageId())) {
          $block = $block->getTranslation($this->currentLanguageId());
        }
        $blocks[] = $block;
      }
    }
    return $blocks;
  }

  /**
   * 创建翻译
   * @param Node $node
   * @param LanguageInterface $source
   * @param LanguageInterface $target
   * @return JsonResponse
   */
  public function landingPageTranslations(Node $node, LanguageInterface $source, LanguageInterface $target) {
    if ($node->bundle() !== 'landing_page') {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Invalid content type',
      ]);
    }
    $trans_manager = \Drupal::moduleHandler()->moduleExists('content_translation') ? \Drupal::service('content_translation.manager') : FALSE;
    if (empty($trans_manager) || !$trans_manager->isEnabled($node->getEntityTypeId(), $node->bundle())) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Translation not enabled.',
      ]);
    }
    $data = [];
    // In case of a pending revision, make sure we load the latest
    // translation-affecting revision for the source language, otherwise the
    // initial form values may not be up-to-date.
    if (!$node->isDefaultRevision() && ContentTranslationManager::isPendingRevisionSupportEnabled($node->id(), $node->bundle())) {
      /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
      $storage = $this->entityTypeManager()->getStorage($node->getEntityTypeId());
      $revision_id = $storage->getLatestTranslationAffectedRevisionId($node->id(), $source->getId());
      if ($revision_id != $node->getRevisionId()) {
        $node = $storage->loadRevision($revision_id);
      }
    }
    try {
      /** @var Node $trans */
      $trans = $node->addTranslation($target->getId(), $node->toArray());
      $time = time();
      $trans->setCreatedTime($time);
      $trans->setChangedTime($time);
      $trans->setOwnerId($this->currentUser()->id());
      $trans->setNewRevision();
      $json = $this->getRequest(FALSE);
      if (!empty($json['title'])) {
        $trans->set('title', $json['title']);
      }
      if ($json['body']) {
        $this->saveLayout($trans, $this->getBlockContent($json['body'], TRUE, $target->getId()), TRUE);
      } else {
        /** @var BlockContent $block */
        foreach ($this->getPanelBlocks($node) as $block) {
          $this->addBlockTranslation($block, $target->getId());
        }
      }
      $trans->save();
    } catch (\Exception $exception) {
      $this->setMessage($exception->getMessage());
    }
    $data['status'] = empty($this->getMessage());
    $data['message'] = $this->getMessage() ?? '';
    return new JsonResponse($data);
  }

  /**
   * add block translation
   * @param BlockContent $block
   * @param $langcode
   * @return BlockContent|\Drupal\Core\Entity\ContentEntityBase
   */
  private function addBlockTranslation(BlockContent $block, $langcode) {
    $trans_manager = \Drupal::moduleHandler()->moduleExists('content_translation') ? \Drupal::service('content_translation.manager') : FALSE;
    if ($trans_manager && $trans_manager->isEnabled($block->getEntityTypeId(), $block->bundle())) {
      if (!$block->hasTranslation($langcode)) {
        $block->addTranslation($langcode, $block->toArray());
        $block->save();
        return $block->getTranslation($langcode);
      }
    }

    return $block->hasTranslation($langcode) ? $block->getTranslation($langcode) : $block;
  }

  /**
   * @return string
   */
  private function currentLanguageId() {
    return $this->languageManager()->getCurrentLanguage()->getId();
  }
}
