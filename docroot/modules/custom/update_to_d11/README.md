# update_to_d11

XINSHI CMS 升级 Drupal 11 的统一处理模块。本模块只在升级期间使用，升级完成并验证稳定后可整体卸载。

## Panelizer → Layout Builder 迁移

panelizer 5.x 没有 Drupal 11 版本，本模块在 Drupal 10 阶段把 panelizer 字段数据迁移到
Layout Builder（`layout_builder__layout`），迁移后 SPA 前端的 JSON 契约保持不变。
后台 panels IPE 可视化编辑随 panelizer 一并退役（编辑走 SPA 着陆页构建器）。

执行顺序（生产环境执行前先 `drush sql-dump` 备份）：

```bash
drush en update_to_d11 -y

# 1. 迁移：panelizer 字段 → layout_builder__layout（含所有翻译）
drush update-to-d11:panelizer-migrate

# 2. 验证：逐翻译比对块 uuid 序列是否一致
drush update-to-d11:panelizer-verify

# 3. 验证通过后清理（不可逆：删除 panelizer 字段与数据、ECK panelizer_display_attribute、卸载模块）
drush update-to-d11:panelizer-cleanup

# 4. 移除 vendor 中的孤儿锁定包
composer update drupal/panelizer drupal/panels drupal/panels_ipe
```

> 注意：`panelizer-cleanup` 内部会调用 `field_purge_batch()`，若站点残留
> 「实体类型已不存在的待删除字段」（如 workspace-upstream）会直接中断，
> 需先执行下方的 `update-to-d11:stale-field-cleanup`。

## 待删除字段脏数据清理（workspace-upstream）

站点历史上启用过 workspaces（后禁用），其 base field `upstream` 被标记删除后残留在
`field.storage.deleted` / `field.field.deleted` 状态里。由于 `workspace` 实体类型已不存在，
`field_purge_batch()` 遍历到它时会抛 `The "workspace" entity type does not exist`，
阻塞所有字段清理（含 panelizer 清理）。本命令统一移除这类「实体类型已不存在的
待删除字段」：

```bash
# 1. 移除实体类型已不存在的待删除字段（如 workspace-upstream）
drush update-to-d11:stale-field-cleanup
```

## CKEditor 4 → CKEditor 5 迁移

核心 CKEditor 4（`drupal/ckeditor`）随 Drupal 11 移除。本模块把全部文本编辑器
（basic_html、full_html、webform_default）迁移到核心 CKEditor 5：

- 核心按钮由 `SmartDefaultSettings` 自动映射（含 source editing 增补）
- 手工补充 contrib 按钮等价物：FontSize → fontSize（plugin_pack font）、
  CodeSnippet → codeBlock（核心）、Maximize → fullscreen（plugin_pack fullscreen）
- 丢弃无等价物的按钮：textindent、ImceImage（存量内容不受影响：
  full_html 未启用 filter_html，内联样式保留）
- basic_html 的 filter_html `allowed_html` 补充 `<span class>`（fontSize 输出所需）

执行顺序：

```bash
# 1. 迁移：自动安装 ckeditor5 + plugin_pack（font/fullscreen），转换全部编辑器
drush update-to-d11:ckeditor4-migrate

# 2. 人工复核：管理后台「文本格式和编辑器」各格式的 toolbar 与插件设置，
#    并在编辑器里回归富文本编辑（字号、代码块、全屏、图片上传）

# 3. 复核通过后清理（卸载 ckeditor、ckeditor_font、codesnippet、ckeditor_textindent）
drush update-to-d11:ckeditor4-cleanup

# 4. 移除 vendor 中的孤儿锁定包
composer update drupal/ckeditor drupal/ckeditor_font drupal/codesnippet drupal/ckeditor_textindent
```

## conflict / key_value 卸载

conflict（仅 beta 支持 D11）与 key_value（不支持 D11）仅为内容锁场景引入，
站点已无启用模块依赖二者，直接卸载移除两个 D11 阻塞包：

```bash
# 1. 卸载模块（conflict.settings.yml 配置随之删除）
drush update-to-d11:conflict-cleanup

# 2. 移除 vendor 中的孤儿锁定包
composer update drupal/conflict drupal/key_value
```

## seven 主题退役

seven 核心版随 Drupal 11 移除（backport 包 drupal/seven 2.0 仅 beta）。
后台已启用 gin，把 admin theme 切到 gin 后卸载 seven：

```bash
# 1. admin theme 若为 seven 则切 gin（gin 未安装时自动安装），随后卸载 seven 主题
drush update-to-d11:seven-cleanup

# 2. 人工复核：后台 /user/login 等页面以 gin 呈现是否正常

# 3. 移除 vendor 中的孤儿锁定包
composer update drupal/seven
```

## 遗留核心模块清理（color / rdf / tour / switch_page_theme）

color、rdf（D10 已从 core 移除的 contrib 回退）、tour（D11 core 弃用）与
switch_page_theme（无 D11 版本）在升级 D11 前统一卸载，并把弃用的 standard
安装 profile 切换到 minimal：

```bash
# 1. 卸载 color/rdf/tour/switch_page_theme，并切换 profile standard → minimal
drush update-to-d11:legacy-cleanup

# 2. 移除 vendor 中的孤儿锁定包
composer update drupal/color drupal/rdf drupal/switch_page_theme
```

quickedit 从 1.x 升到 2.x（2.0 支持 `^10.2 || ^11`，D10 阶段即可执行）；
ckeditor_templates、ckeditor_templates_ui、colorbutton（及其依赖 panelbutton）
已从 composer.json require 移除：

```bash
composer update drupal/quickedit --with-all-dependencies
```

## entity_theme_engine D11 支持

entity_theme_engine 8.x-1.7 的 info 约束只到 `^10`，但代码审计与上游
8.x-1.x-dev（2024-12，core_compatibility 已含 `^11`）对比确认：D11 支持
只是约束问题，PHP 代码无 API 阻塞（dev 版差异均为加固性变更：Twig 错误
处理、help hook、空引用修复、测试）。

composer.json 已把版本约束从 `^1.7` 改为 `1.x-dev`（官方 dev 分支），
容器内执行生效：

```bash
composer update drupal/entity_theme_engine
```

注意：切核心到 `^11` 后需实测全站 JSON 渲染（landingPage、news、taxonomy
等走 entity_widget 的端点）确认 normalizer 在 Symfony 7 下行为不变。

## Commerce 2 → 3（约束升级，代码未适配）

站点未启用 Commerce（core.extension 与 config/sync 均无 commerce 痕迹），
升级仅解除 composer 对 core ^11 的阻塞：composer.json 约束 `^2.40` → `^3.3`。

```bash
# 容器内更新包（Commerce 3 支持 ^10.3 || ^11，D10 阶段即可执行）
composer update drupal/commerce --with-all-dependencies
```

注意：`xinshi_commerce`（未启用）的 PaymentGateway/CheckoutPane/PluginForm
代码仍为 Commerce 2 API，且依赖已不存在的 yunke_pay 模块——**启用前**需按
Commerce 3 迁移指南整体重写。已启用模块
（xinshi_jsonapi/xinshi_manage/xinshi_views）对 commerce 仅为路由字符串与
惰性实体查询，无 PHP 类依赖，不受影响。

## alipaysdk 死依赖移除

`alipaysdk/openapi` 在代码中零引用（xinshi_commerce 的 Alipay 网关用的是
从未安装的 `Alipay\EasySDK`，短信走 AlibabaCloud SDK），且其 vendor 文件
存在未提交的本地改动（正是移除动机）。已从 composer require 删除并
git rm 整个 vendor/alipaysdk（vendor 在本仓库被完整跟踪）。

```bash
# 容器内同步 lock（否则 composer install 会按旧 lock 恢复该包）
composer update alipaysdk/openapi --with-all-dependencies
```

## 后续 D11 升级处理约定

升级 Drupal 11 过程中的其它处理（弃用 API 清理、其它阻塞模块的替代/补丁等）
统一放在本模块，按各自主题添加 Drush 命令或 update hook。
