# update_to_d11

XINSHI CMS（ls-builder-cms）升级 Drupal 11 的统一处理模块。本模块只在升级期间使用，
升级完成并验证稳定后可整体卸载。

> 本模块移植自姊妹项目 xinshi-cms-10，架构一致（同样的 ECK `panelizer_display_attribute`、
> 同样的 panelizer bundle、同样的 entity_theme_engine JSON 渲染路径），仅对项目差异做了适配。

## 生产升级总顺序（重要）

11.x 分支一次性完成核心切换与阻塞包移除（panelizer、ckeditor4 系、conflict、
key_value、switch_page_theme、alipaysdk 等 vendor 包随分支删除），
其中 **panelizer 字段数据只有在 panelizer 模块文件存在时才能读取**，
因此生产升级必须按以下顺序执行：

```bash
# ── 第 1 阶段：仍在 10.x 代码上执行（所有命令依赖旧模块文件在场）──
drush sql-dump > backup-d10.sql          # 备份
drush en update_to_d11 -y

# panelizer 序列（migrate 读 panelizer 字段、cleanup 删字段并卸载模块，
# 都依赖 panelizer 文件存在）
drush update-to-d11:panelizer-migrate
drush update-to-d11:panelizer-verify
drush update-to-d11:panelizer-cleanup

# CKEditor 序列（SmartDefaultSettings 需要 ckeditor4 存在；
# migrate 与 cleanup 之间人工复核「文本格式和编辑器」并回归富文本编辑）
drush update-to-d11:ckeditor4-migrate
drush update-to-d11:ckeditor4-cleanup

# 其余阻塞模块卸载
drush update-to-d11:conflict-cleanup
drush update-to-d11:switch-page-theme-replace

# ── 第 2 阶段：部署 11.x 代码（composer 产物已提交，无需容器内 composer）──
drush updb -y                            # 含 D11 核心与 contrib 大版本更新
drush cr && drush ws --tail

# ── 第 3 阶段：回归验证 ──────────────────────────────────────
# entity_theme_engine 全站 JSON 渲染（landing_page、news、taxonomy 等走
# entity_widget 的端点），SPA 各端点比对，详见下文各节
```

若阶段 1 有命令漏跑，conflict/switch_page_theme 两个命令在 11.x 代码
上仍可安全补跑（已内置文件缺失兜底，直接清 core.extension 残留）；
但 panelizer 与 ckeditor4 序列**无法**在 11.x 代码上补跑，只能回 10.x 补跑
或从备份重来——**不要跳过阶段 1**。

## Panelizer → Layout Builder 迁移

panelizer 5.x 没有 Drupal 11 版本，本模块在 Drupal 10 阶段把 panelizer 字段数据迁移到
Layout Builder（`layout_builder__layout`），迁移后 SPA 前端的 JSON 契约保持不变。
后台 panels IPE 可视化编辑随 panelizer 一并退役（编辑走 SPA 着陆页构建器）。

本项目使用 panelizer 的 bundle：`job`、`landing_page`。

执行顺序（生产环境执行前先 `drush sql-dump` 备份）：

```bash
drush en update_to_d11 -y

# 1. 迁移：panelizer 字段 → layout_builder__layout（含所有翻译）
drush update-to-d11:panelizer-migrate

# 2. 验证：逐翻译比对块 uuid 序列是否一致
drush update-to-d11:panelizer-verify

# 3. 验证通过后清理（不可逆：删除 panelizer 字段与数据、ECK panelizer_display_attribute、卸载模块）
drush update-to-d11:panelizer-cleanup
```

vendor 中的 panelizer/panels/panels_ipe 孤儿锁定包已随 11.x 分支的核心切换一并移除。

## CKEditor 4 → CKEditor 5 迁移

核心 CKEditor 4（`drupal/ckeditor`）随 Drupal 11 移除。本模块把全部文本编辑器
（basic_html、full_html、webform_default）迁移到核心 CKEditor 5：

- 核心按钮由 `SmartDefaultSettings` 自动映射（含 source editing 增补）
- 手工补充 contrib 按钮等价物：FontSize → fontSize（plugin_pack font）、
  CodeSnippet → codeBlock（核心）、Maximize → fullscreen（plugin_pack fullscreen）
- 丢弃无等价物的按钮：textindent、ImceImage（存量内容不受影响：
  full_html 未启用 filter_html，内联样式保留）
- basic_html 的 filter_html `allowed_html` 补充 `<span class>`（fontSize 输出所需）

> ⚠️ 前置条件：`ckeditor5_plugin_pack`（含 font/fullscreen 子模块）尚未在本项目
> composer.json 中。`ckeditor4-migrate` 会 `module_installer->install()` 这些模块，
> 执行前需先在 composer 引入 `drupal/ckeditor5_plugin_pack`（建议放在 11.x 分支的
> composer 切换里一起处理，或先在本分支单独 `composer require`）。

执行顺序：

```bash
# 1. 迁移：自动安装 ckeditor5 + plugin_pack（font/fullscreen），转换全部编辑器
drush update-to-d11:ckeditor4-migrate

# 2. 人工复核：管理后台「文本格式和编辑器」各格式的 toolbar 与插件设置，
#    并在编辑器里回归富文本编辑（字号、代码块、全屏、图片上传）

# 3. 复核通过后清理（卸载 ckeditor、ckeditor_font、ckeditor_templates、
#    ckeditor_templates_ui、ckeditor_textindent、codesnippet、colorbutton）
drush update-to-d11:ckeditor4-cleanup
```

vendor 中的相关孤儿锁定包已随 11.x 分支的核心切换一并移除。

## conflict / key_value 卸载

conflict（仅 beta 支持 D11）与 key_value（不支持 D11）仅为内容锁场景引入，
站点已无启用模块依赖二者，直接卸载移除两个 D11 阻塞包：

```bash
drush update-to-d11:conflict-cleanup
```

vendor 中的 drupal/conflict、drupal/key_value 包已随 11.x 分支的核心切换一并移除。
模块文件已缺失时补跑本命令，会直接清理 core.extension 残留记录与 key_value_sorted 表。

## switch_page_theme 替代

switch_page_theme 4.x 不支持 D11。update_to_d11 内置等效主题协商器
（`ManageThemeNegotiator`，priority 1，路径 + 别名 matchPath 匹配，
规则存 `update_to_d11.settings` 的 `switch_page_theme_rules` 键）。
角色/语言过滤未迁移（站点原配置均为"不过滤"）。

本项目 switch_page_theme 规则的 theme 为 `cms_admin`（`/manage/*`、`/user/*` 等路径）。

```bash
# 1. 迁移规则并卸载 switch_page_theme（先写新规则再卸载，切换即时生效）
drush update-to-d11:switch-page-theme-replace

# 2. 人工复核：/manage/*、/user/login 等页面仍以 cms_admin 主题渲染
```

vendor 中的 drupal/switch_page_theme 包已随 11.x 分支的核心切换一并移除。

## seven 主题（本项目预计为 no-op）

本项目 core.extension 的 theme 仅 `claro` + `gin`，无 seven；`system.theme.yml`
不在 config/sync（被 config_ignore 忽略）。`update-to-d11:seven-cleanup` 保留作
防御：若线上 DB 的 `system.theme` 仍指向 seven，会切到 gin 并卸载 seven，否则直接
报告"无需处理"。

## 其余阻塞项（11.x 分支的 composer 切换层，未在本模块）

以下处理属于核心切换 / composer 层，随 11.x 分支执行，不在本模块 Drush 命令内：

- `entity_theme_engine` 8.x-1.7 info 约束只到 `^10`，且其 Normalizer 在 D11/Symfony 7 下
  `drush cr` 直接 fatal——需切 1.x-dev 并打 composer patch（`getSupportedTypes()` +
  `normalize()` 签名），参考姊妹项目的 `patchs/entity_theme_engine-symfony7-normalizer-signature.patch`
- `alipaysdk/openapi` 死依赖移除（本项目自定义模块零引用）
- 49 个未启用 contrib 包的批量移除
- `rdf`（core → contrib `^3.0@beta`）、`color`（core → contrib `^2.0@alpha`）、
  `quickedit`（core → contrib `^1.0`）的机器名迁移
- 手工管理模块（`json_editor`、`otp_login` 等不在 composer 的）info 补 `|| ^11`

## 后续 D11 升级处理约定

升级 Drupal 11 过程中的其它处理（弃用 API 清理、其它阻塞模块的替代/补丁等）
统一放在本模块，按各自主题添加 Drush 命令或 update hook。
