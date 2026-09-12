<?php


namespace Drupal\xinshi_api\Controller;


use Drupal\block_content\Entity\BlockContent;
use Drupal\Component\Serialization\Json;
use Drupal\content_translation\ContentTranslationManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\xinshi_api\NodeJson;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Class PanelsIPEPageController
 * @package Drupal\xinshi_api\Controller
 */
class PanelsIPEPageController extends ControllerBase {

  /**
   * 历史版本列表最多返回的条数
   */
  const REVISION_LIMIT = 50;

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
        $builder = new NodeJson($entity);
        if ($builder->isLayoutBuilder()) {
          $this->saveLayoutBuilder($entity, $json['body']);
        }
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
      $block_json = $this->formatBlocks($this->getPanelBlocks($node));
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
   * 历史版本列表
   *
   * @param Node $node
   * @return JsonResponse
   */
  public function landingPageRevisions(Node $node) {
    if ($node->bundle() !== 'landing_page') {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Invalid content type',
      ]);
    }
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage('node');
    $langcode = $this->currentLanguageId();
    // 页面本身没有当前语言的版本时（例如内容语言为“未指定”，或站点默认语言
    // 变更过），按语言过滤只会得到空列表，这种情况下退回列出全部修订
    $filter_by_language = $node->hasTranslation($langcode);
    // revisionIds 按修订 ID 升序返回，倒序后从最近的一条开始
    $vids = array_reverse($storage->revisionIds($node));
    $revisions = [];
    foreach ($vids as $vid) {
      // 条数限制要在按语言过滤之后才算得准，因此放在循环里
      if (count($revisions) >= self::REVISION_LIMIT) {
        break;
      }
      /** @var Node $revision */
      $revision = $storage->loadRevision($vid);
      if (empty($revision)) {
        continue;
      }
      if ($filter_by_language) {
        // 一个节点的修订由所有翻译共享：改英文也会生成修订，不过滤的话中文列表
        // 里会出现时间和内容都一样的重复行，而早于翻译创建的修订还会退回源语言
        // 的内容。判断方式与 Drupal 自带修订页一致：必须有当前语言，且这次修订
        // 确实改动了当前语言
        if (!$revision->hasTranslation($langcode)) {
          continue;
        }
        $revision = $revision->getTranslation($langcode);
        if (!$revision->isRevisionTranslationAffected()) {
          continue;
        }
      }
      // panelizer 保存时不会写 revision_uid，回退到内容作者
      $author = $revision->getRevisionUser() ?: $revision->getOwner();
      $revisions[] = [
        'vid' => $revision->getRevisionId(),
        'current' => $revision->getRevisionId() == $node->getRevisionId(),
        'changed' => $revision->getChangedTime(),
        'log' => $revision->getRevisionLogMessage() ?? '',
        'author' => $author ? $author->getDisplayName() : '',
      ];
    }
    return new JsonResponse([
      'status' => TRUE,
      'message' => '',
      'nid' => $node->id(),
      'uuid' => $node->uuid(),
      'vid' => $node->getRevisionId(),
      'langcode' => $langcode,
      'revisions' => $revisions,
    ]);
  }

  /**
   * 指定历史版本的页面 JSON
   *
   * 结构与 landingPageCanonical 一致，前端可直接载入草稿。
   *
   * @param Node $node
   * @param string $vid
   * @return JsonResponse
   */
  public function landingPageRevisionCanonical(Node $node, $vid) {
    if ($node->bundle() !== 'landing_page') {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Invalid content type',
      ]);
    }
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage('node');
    /** @var Node $revision */
    $revision = $storage->loadRevision($vid);
    if (empty($revision) || $revision->id() != $node->id()) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Invalid revision',
      ]);
    }
    $langcode = $this->currentLanguageId();
    if ($revision->hasTranslation($langcode)) {
      $revision = $revision->getTranslation($langcode);
    }
    $data = [];
    $block_json = $this->formatBlocks($this->getRevisionBlocks($revision));
    $data['status'] = empty($this->getMessage());
    $data['message'] = $this->getMessage() ?? '';
    if ($data['status']) {
      $title = "[node:title] | [site:name]";
      $data['title'] = \Drupal::token()->replace($title, ['node' => $revision]);
      $data['uuid'] = $node->uuid();
      $data['nid'] = $node->id();
      // vid/changed 取当前默认修订：载入历史版本只替换内容，保存时仍然是在最新修订
      // 之上提交，landingPageUpdate 的并发校验和前端的过期轮询都读这两个值。
      $data['vid'] = $node->getRevisionId();
      $data['changed'] = $node->getChangedTime();
      $data['revision_vid'] = $revision->getRevisionId();
      $data['revision_changed'] = $revision->getChangedTime();
      $data['langcode'] = $revision->language()->getId();
      $data['label'] = $revision->label();
      $data['body'] = $block_json;
    }
    return new JsonResponse($data);
  }

  /**
   * 区块转成页面 JSON 的 body 结构
   *
   * @param array $blocks
   * @return array
   */
  private function formatBlocks(array $blocks) {
    $block_json = [];
    /** @var BlockContent $block */
    foreach ($blocks as $block) {
      if ($block->bundle() !== 'json') {
        $this->setMessage('This page contains non Json blocks and cannot be edited.');
        return [];
      }
      $block_json[] = [
        'uuid' => $block->uuid(),
        'id' => $block->id(),
        'type' => $block->bundle(),
        'langcode' => $block->language()->getId(),
        'attributes' => [
          'body' => Json::decode(htmlspecialchars_decode($block->get('body')->value)) ?? Json::decode($block->get('body')->value),
        ],
      ];
    }
    return $block_json;
  }

  /**
   * 取回某个修订当时的区块
   *
   * 布局里固定了区块的修订 ID（panelizer 的 blocks[].vid、layout builder 的
   * block_revision_id），只有按该 ID 载入才是当时的内容，按 uuid 载入拿到的
   * 永远是最新内容。
   *
   * @param Node $revision
   * @return array
   */
  private function getRevisionBlocks(Node $revision) {
    $blocks = [];
    $storage = $this->entityTypeManager()->getStorage('block_content');
    $json = new NodeJson($revision, 'full');
    if ($json->isLayoutBuilder()) {
      foreach ($revision->get(OverridesSectionStorage::FIELD_NAME) as $item) {
        /** @var \Drupal\layout_builder\Section $section */
        $section = $item->section;
        foreach ($section->getComponents() as $component) {
          $rev_id = $component->get('configuration')['block_revision_id'] ?? NULL;
          $block = $rev_id ? $storage->loadRevision($rev_id) : NULL;
          if ($block) {
            $blocks[] = $this->getBlockTranslation($block);
          }
        }
      }
    }
    return $blocks;
  }

  /**
   * 当前语言的区块翻译，没有翻译时返回原区块
   *
   * @param BlockContent $block
   * @return BlockContent
   */
  private function getBlockTranslation(BlockContent $block) {
    $langcode = $this->currentLanguageId();
    return $block->hasTranslation($langcode) ? $block->getTranslation($langcode) : $block;
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
        $builder = new NodeJson($node);
        if ($builder->isLayoutBuilder()) {
          $this->saveLayoutBuilder($node, $json['body']);
        }
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
      $this->setMessage($this->t('Invalid revision ID'));
      return FALSE;
    }
    if ($vid == $node->getRevisionId()) {
      return TRUE;
    }
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage('node');
    /** @var Node $revision */
    $revision = $storage->loadRevision($vid);
    if (empty($revision) || $node->id() != $revision->id()) {
      $this->setMessage($this->t('Invalid revision'));
      return FALSE;
    }
    if ($revision->hasTranslation($this->currentLanguageId())) {
      $revision = $revision->getTranslation($this->currentLanguageId());
    }
    if ($this->layoutSignature($node) === $this->layoutSignature($revision)) {
      return TRUE;
    } else {
      $this->setMessage($this->t('The content has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved.'));
      return FALSE;
    }
  }

  /**
   * 计算节点布局字段的签名，用于并发编辑检测。
   *
   * @param Node $node
   * @return string
   */
  private function layoutSignature(Node $node) {
    $field = OverridesSectionStorage::FIELD_NAME;
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    return serialize($node->get($field)->getValue());
  }


  /**
   * 获取区块
   * @param Node $node
   * @return array
   */
  private function getPanelBlocks(Node $node) {
    $blocks = [];
    $json = new NodeJson($node, 'full');
    if ($json->isLayoutBuilder()) {
      $blocks = $this->getLayoutBuilderBlocks($node);
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
      // 路由上的 {source} 之前是被忽略的：请求 URL 不带语言前缀，参数转换器按当前
      // 语言上溯，$node 永远是默认语言那份。按 source 取翻译，"从简体创建繁体"才
      // 真的以简体为源。
      $source_node = $node->hasTranslation($source->getId())
        ? $node->getTranslation($source->getId())
        : $node->getUntranslated();
      /** @var Node $trans */
      $trans = $node->addTranslation($target->getId(), $source_node->toArray());
      $time = time();
      $trans->setCreatedTime($time);
      $trans->setChangedTime($time);
      $trans->setOwnerId($this->currentUser()->id());
      $trans->setNewRevision();
      // toArray() 复制过来的 path 里带着源语言别名记录的 pid。核心 PathItem::postSave()
      // 一见到 pid 就只改写那条已有记录，目标语言拿不到自己的 path_alias（URL 退回
      // /<lang>/node/N），源语言的别名反而可能被改掉。清掉 pid 才会走新建分支。
      $trans->set('path', [
        'alias' => $source_node->get('path')->alias ?: '',
        'pid' => NULL,
        'langcode' => $target->getId(),
      ]);
      $json = $this->getRequest(FALSE);
      if (!empty($json['title'])) {
        $trans->set('title', $json['title']);
      }
      $builder = new NodeJson($node);
      if (!empty($json['body'])) {
        if ($builder->isLayoutBuilder()) {
          $this->saveLayoutBuilder($trans, $json['body'], TRUE, $target->getId());
        }
      } else {
        if ($builder->isLayoutBuilder()) {
          // Layout Builder 下 inline_block 的 block_revision_id 锁定了具体修订，
          // 仅给 block_content 加翻译并不会让译文出现在前台 —— 必须把 $trans 的
          // layout 字段中各组件的 block_revision_id 同步更新为新生成的修订。
          $this->cloneLayoutBuilderTranslations($trans, $target->getId());
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
   * 删除翻译
   * @param Node $node
   * @param LanguageInterface $langcode
   * @return JsonResponse
   */
  public function landingPageTranslationsDelete(Node $node, LanguageInterface $langcode) {
    $langcode_id = $langcode->getId();
    if ($node->bundle() !== 'landing_page') {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Invalid content type',
      ]);
    }
    // 默认翻译不允许删除。
    if ($langcode_id === $node->getUntranslated()->language()->getId()) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Cannot delete the default translation.',
      ]);
    }
    // 检查是否存在对应的语言翻译。
    if (!$node->hasTranslation($langcode_id)) {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Translation does not exist.',
      ]);
    }
    try {
      // 核心在移除翻译时按 (path, langcode) 批量删 path_alias
      // （PathFieldItemList::delete()），只要有一条记录的 langcode 是脏的，别的语言
      // 的 URL 就会跟着消失。先记下其它语言当前生效的别名，保存后补回来。
      $aliases = $this->getNodeAliases($node, [$langcode_id]);
      $node->removeTranslation($langcode_id);
      $node->save();
      $this->restoreNodeAliases($node, $aliases);
      $data = [
        'status' => TRUE,
        'message' => $this->t('Translation deleted.'),
      ];
    } catch (\Exception $exception) {
      $data = [
        'status' => FALSE,
        'message' => $exception->getMessage(),
      ];
    }
    return new JsonResponse($data);
  }

  /**
   * 创建普通内容类型的节点翻译
   *
   * 核心 JSON:API 只能读取和更新已存在的翻译（EntityUuidConverter 对缺失翻译的
   * PATCH 直接 405），创建必须走这里。端点只负责创建：把源语言的值复制过去，
   * 字段值随后由前端按语言前缀 PATCH 写入新翻译。
   *
   * @param Node $node
   * @param LanguageInterface $source
   * @param LanguageInterface $target
   * @return JsonResponse
   */
  public function nodeTranslations(Node $node, LanguageInterface $source, LanguageInterface $target) {
    if ($node->bundle() === 'landing_page') {
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
    // 幂等：翻译已存在（例如创建成功后 PATCH 失败重试）直接返回成功，
    // 让前端继续 PATCH 字段值。
    if ($node->hasTranslation($target->getId())) {
      return new JsonResponse([
        'status' => TRUE,
        'message' => 'Translation exists.',
      ]);
    }
    try {
      $source_node = $node->hasTranslation($source->getId())
        ? $node->getTranslation($source->getId())
        : $node->getUntranslated();
      /** @var Node $trans */
      $trans = $node->addTranslation($target->getId(), $source_node->toArray());
      $time = time();
      $trans->setCreatedTime($time);
      $trans->setChangedTime($time);
      $trans->setOwnerId($this->currentUser()->id());
      $trans->setNewRevision();
      // toArray() 复制过来的 path 里带着源语言别名记录的 pid。核心 PathItem::postSave()
      // 一见到 pid 就只改写那条已有记录，目标语言拿不到自己的 path_alias。清掉 pid
      // 才会走新建分支（同 landingPageTranslations）。
      $trans->set('path', [
        'alias' => $source_node->get('path')->alias ?: '',
        'pid' => NULL,
        'langcode' => $target->getId(),
      ]);
      $trans->save();
    } catch (\Exception $exception) {
      $this->setMessage($exception->getMessage());
    }
    $data['status'] = empty($this->getMessage());
    $data['message'] = $this->getMessage() ?? '';
    return new JsonResponse($data);
  }

  /**
   * 写入着陆页 URL 别名
   *
   * 走 JSON:API PATCH 节点 path 字段写不进来：核心 EntityResource::updateEntityField()
   * 用 $origin->getValue() 往目标实体复制，而 Map::getValue() 跳过 computed 属性，
   * pathauto 恰恰是 computed —— 请求里的 pathauto=0 到不了保存时刻，
   * PathautoItem::postSave() 便按节点存量状态（builder 建的页是 CREATE）跳过核心
   * PathItem::postSave()，而站点又没有 pattern，别名两头落空。服务端在同一个字段
   * 对象上 set，没有这段 getValue 往返，属性不会丢。
   *
   * 各语言别名只差前缀（前端切语言只换前缀），所以同一个别名写给所有翻译；一次
   * save 即可，核心保存时会对每个翻译分别调用 postSave。
   *
   * @param Node $node
   * @return JsonResponse
   */
  public function landingPageAlias(Node $node) {
    if ($node->bundle() !== 'landing_page') {
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Invalid content type',
      ]);
    }
    $json = $this->getRequest(FALSE);
    $alias = trim($json['alias'] ?? '');
    if ($alias === '') {
      return new JsonResponse([
        'status' => FALSE,
        'message' => $this->t('Missing alias'),
      ]);
    }
    if (strpos($alias, '/') !== 0) {
      $alias = '/' . $alias;
    }
    try {
      $langcodes = array_keys($node->getTranslationLanguages());
      foreach ($langcodes as $langcode) {
        $translation = $node->getTranslation($langcode);
        $translation->set('path', [
          'alias' => $alias,
          // 已有别名记录就地改写，没有的（如新翻译）交给核心走新建分支。
          'pid' => $translation->get('path')->pid,
          'langcode' => $langcode,
          // 标记为手动别名，等同后台取消勾选"生成自动 URL 别名"。没装 pathauto
          // 的站点上 path 字段没有这个属性，核心不读它，留着也无害。
          'pathauto' => 0,
        ]);
      }
      $node->save();
      $data = [
        'status' => TRUE,
        'message' => $this->t('Alias updated.'),
        'data' => [
          'alias' => $alias,
          'langcodes' => $langcodes,
        ],
      ];
    } catch (\Exception $exception) {
      $data = [
        'status' => FALSE,
        'message' => $exception->getMessage(),
      ];
    }
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

  /**
   * 节点在各语言下当前生效的别名，键为语言代码
   *
   * @param Node $node
   * @param array $skip 要跳过的语言代码
   * @return array
   */
  private function getNodeAliases(Node $node, array $skip = []) {
    $aliases = [];
    $path = '/node/' . $node->id();
    /** @var \Drupal\path_alias\AliasRepositoryInterface $repository */
    $repository = \Drupal::service('path_alias.repository');
    foreach (array_keys($node->getTranslationLanguages()) as $langcode) {
      if (in_array($langcode, $skip, TRUE)) {
        continue;
      }
      if ($record = $repository->lookupBySystemPath($path, $langcode)) {
        $aliases[$langcode] = $record['alias'];
      }
    }
    return $aliases;
  }

  /**
   * 补回保存过程中被连带删掉的别名
   *
   * lookupBySystemPath 自带 und 回退，仍能解析出别名的语言会被跳过，不会产生重复
   * 记录；补建时按语言写入正确的 langcode，顺带修掉历史脏数据。
   *
   * @param Node $node
   * @param array $aliases getNodeAliases() 取的快照
   */
  private function restoreNodeAliases(Node $node, array $aliases) {
    if (empty($aliases)) {
      return;
    }
    $path = '/node/' . $node->id();
    /** @var \Drupal\path_alias\AliasRepositoryInterface $repository */
    $repository = \Drupal::service('path_alias.repository');
    $storage = $this->entityTypeManager()->getStorage('path_alias');
    foreach ($aliases as $langcode => $alias) {
      if ($repository->lookupBySystemPath($path, $langcode)) {
        continue;
      }
      $storage->create([
        'path' => $path,
        'alias' => $alias,
        'langcode' => $langcode,
      ])->save();
    }
  }

  /**
   * 为 Layout Builder 节点的所有 inline_block 创建目标语言翻译，
   * 并把 $trans 上对应组件的 block_revision_id 更新为最新修订，
   * 否则前台仍按旧 revision 渲染、看不到译文。
   *
   * 注意：本方法不会再调用 $trans->save()，由调用方统一保存。
   *
   * @param Node $trans
   * @param string $langcode
   */
  private function cloneLayoutBuilderTranslations(Node $trans, $langcode) {
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $layout_field */
    $layout_field = $trans->get(OverridesSectionStorage::FIELD_NAME);
    $storage = $this->entityTypeManager()->getStorage('block_content');

    foreach ($layout_field as $delta => $item) {
      /** @var \Drupal\layout_builder\Section $section */
      $section = $item->section;
      $changed = FALSE;
      foreach ($section->getComponents() as $component) {
        $config = $component->get('configuration');
        $rev_id = $config['block_revision_id'] ?? NULL;
        if (empty($rev_id)) {
          continue;
        }
        /** @var BlockContent $revision */
        $revision = $storage->loadRevision($rev_id);
        if (!$revision || $revision->bundle() !== 'json') {
          continue;
        }
        // addBlockTranslation 基于默认修订操作，先取最新默认实体。
        /** @var BlockContent $latest */
        $latest = $storage->load($revision->id());
        if (!$latest) {
          continue;
        }
        $translated = $this->addBlockTranslation($latest, $langcode);
        // addBlockTranslation 内部 save() 会生成新修订；同步给布局组件。
        $config['block_revision_id'] = $translated->getRevisionId();
        $component->setConfiguration($config);
        $changed = TRUE;
      }
      if ($changed) {
        // 重新写回字段项以触发序列化。
        $layout_field->set($delta, ['section' => $section]);
      }
    }
  }

  /**
   * 获取 Layout Builder 的 sections 及每个 section 内的 blocks（BlockContent 实体）
   *
   * @return array
   */
  public function getLayoutBuilderBlocks(Node $node) {
    $json = new NodeJson($node, 'full');
    if (!$json->isLayoutBuilder()) {
      return [];
    }
    $blocks = [];
    // 尝试多种方式获得 sections：优先使用对象方法，其次读取 third_party_settings
    $builder =  $json->getLayoutBuilder()->build($node);
    foreach ($builder['_layout_builder'] as $section_id => $section) {
      if (!is_numeric($section_id)) {
        continue;
      }
      $weight = 0;
      foreach ($section['content'] as $block_id => $component) {
        if ($component['content']['#entity_type'] == 'block_content') {
          $block = $component['content']['#block_content'];
          if ($block->hasTranslation($this->currentLanguageId())) {
            $block = $block->getTranslation($this->currentLanguageId());
          }
          $blocks[] = $block;
        }
      }
    }
    return $blocks;
  }

  private function saveLayoutBuilder(Node &$entity, array $blocks, $add_translations = FALSE, $langcode = NULL) {
    // 获取布局字段（存储layout builder配置的字段）
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $layout_field */
    $layout_field = $entity->get(OverridesSectionStorage::FIELD_NAME);
    $langcode = $langcode ?? $this->currentLanguageId();
    $number = $this->getNumber();
    $layout_field->setValue([]); // 清空现有布局配置

    foreach ($blocks as $row) {
      $uuid = $row['uuid'] ?? '';
      $body = $row['attributes']['body'] ?? '';
      $blocks = $uuid ? $this->entityTypeManager->getStorage('block_content')->loadByProperties(['uuid' => $uuid]) : FALSE;
      /** @var BlockContent $block_content */
      $block_content = $blocks ? reset($blocks) : FALSE;
      if ($block_content && $block_content->bundle() != 'json') {
        continue;
      }
      if (empty($block_content) && $body) {
        $number++;
        $block_content = BlockContent::create([
          'type' => 'json',
          'info' => "Json {$number}",
          'langcode' => [
            'value' => $langcode,
          ],
        ]);
      }
      if (empty($block_content)) {
        continue;
      }

      if ($add_translations) {
        $block_content = $this->addBlockTranslation($block_content, $langcode);
      }
      $block_content->set('body', [
        [
          'value' => is_array($body) ?  json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $body,
          'format' => 'json',
        ],
      ]);
      $block_content->set('reusable', 0);
      $block_content->save();
      $configuration = [
        'id' => 'inline_block:' . $block_content->bundle(),   // 格式必须是 'inline_block:{block_type}'
        'label' =>  $block_content->label(), // 可选：显示给用户的标题
        'label_display' => '0', // 'visible' 显示，'0' 隐藏
        'provider' => 'layout_builder',
        'view_mode' => 'full', // 渲染视图模式
        // 关键配置：指向刚创建的 block_content 的 revision ID
        'block_revision_id' => $block_content->getRevisionId(),
      ];
      // 创建一个新的section，使用单列布局
      // 可用的布局插件ID包括：'layout_onecol', 'layout_twocol_section', 'layout_threecol_section' 等
      $section = new Section('layout_onecol');
      $uuid = \Drupal::service('uuid')->generate();
      $component = new SectionComponent($uuid, 'content', $configuration);
      $section->appendComponent($component);
      $layout_field->appendItem($section);
    }
    $entity->save();
  }
}
