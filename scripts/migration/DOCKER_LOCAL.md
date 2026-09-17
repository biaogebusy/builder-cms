# 在本地 Docker 迁移，再导出数据库

当前目标是 `http://builder-pro.docker.localhost`。应用使用
`builder-cms/docker-compose.yml` 的 `builder-pro` 容器，代码根目录为
`/var/www/html`，公开目录为 `/var/www/html/docroot`。目标默认连接使用
`mariadb:3306` 的 `builder` 库，无表前缀，隔离级别为 `READ COMMITTED`。
数据库凭据保留在站点私密设置中。

本次迁移范围固定为已审核的来源快照、179 张表、365,718 行、530 条默认配置、
88 条语言覆盖及 52 个 Views。微信 Views 已排除，`xinshi_manage` 对应的
所选数据、管理 Views 和角色权限仍在范围内。完整范围和历史核验记录见文档站。

## 本次执行结果（2026-09-16）

本地目标已完成迁移和本轮独立核验，SQL 已导出，本地服务已恢复。
以下结果来自本次 builder 目标；附件本体按用户要求复用线上文件。

| 项目 | 已完成结果 |
| --- | --- |
| 导入与逐行核对 | 179 张表、365,718 行；所有原始列、映射和归属核对通过，0 差异 |
| 模型与配置 | 101 个模块、530 条默认配置、88 条语言覆盖；52 个 Views、200 个控件、91 个格式器通过 |
| 实体与历史 | 全部所选实体和原生修订加载通过，含 11,378 个节点及 24,430 条节点修订 |
| Views 与表单 | 52 个保留 Views、122 个启用显示查询、32 类编辑表单通过 |
| 权限与接口 | 13 项角色/访问检查、16 个读取请求、387 条别名路由、1,460 个页面历史 JSON 读取通过 |
| 用户与归属 | 源 UID 1 映射到 1403；保留独立目标管理员，含匿名账户共 1,204 条用户记录；365,716 条归属记录，另 2 行匿名账户仅映射 |
| SQL | builder-validated-20260916.sql.gz；完整 641 张目标表结构，包含业务数据、配置、历史和 Migrate 映射，运行表只保留结构 |
| 本地访问 | Web、PHP-FPM 和 cron 服务已恢复；通过本地域名与 127.0.0.1 映射验证首页和登录页 HTTP 200 |

SQL、压缩包摘要及完整本轮报告位于
`xinshi-cms/.migration-private/builder-pro-20260916`。代码包和私密规则包分别位于
其 `delivery` 目录；运行批次与恢复点保留在 `builder-cms/.migration-private/batch`。
线上数据库尚未覆盖。原始附件、本地生成密钥、来源连接文件均不放入代码包。

本机默认 DNS 将 builder-pro.docker.localhost 解析为 28.0.0.59，直接 curl
出现空响应。本轮保留系统 DNS 设置，以 127.0.0.1 映射验证 Docker 入口，
请求仍使用原域名。命令行访问可使用：

```sh
curl --noproxy '*' --resolve builder-pro.docker.localhost:80:127.0.0.1 \
  http://builder-pro.docker.localhost/user/login
```

## 当前目标与执行前提

2026-09-16 盘点发现，本地 `builder` 有 530 张表，而目标 SQL 基线只有
57 张表；另外 473 张表包含旧媒体、表单提交和 OAuth 客户端。
用户已批准重建本地库。执行前再次备份全部 530 张表，随后已恢复核对过的
`new-pro.sql.gz`，保留目标站点身份并应用已批准的管理员邮箱。

2026-09-14/15 的全量导入与功能核验属于旧隔离试迁。本次使用新 `builder`
目标及独立恢复点执行完整迁移；恢复的规则已与旧 Migrate map 的全部来源主键核对一致。
S3—S6 已按用户“按建议处理”批准：保留现存记录、历史和原始断链，不补造对象、
不重放草稿创建，继续排除 job / question。

## 私密批次与来源连接

将新规则包解压到 `builder-cms/.migration-private/batch`，确保该目录在
`docroot` 之外。私密目录使用 0700，输入和环境文件使用 0600，并通过该目录内的
`.gitignore` 排除所有内容。不要把旧试迁执行报告或恢复点混入新批次。

本地源库继续复用 `builder-migration-db-20260913` 容器中的
`builder_migration_source`。来源账户只授予 `SELECT` / `SHOW VIEW`。
目标默认连接保持用户配置。应用容器同时需要接入来源的内部网络：

```sh
docker network connect builder-migration-20260913 builder-pro
```

先检查现有网络，只在未连接时执行。该连接不会随应用容器重建保留，重建后需重新接入。

在应用挂载目录之外的主机私密文件中填写
`XINSHI_MIGRATION_SOURCE_DATABASE`、`XINSHI_MIGRATION_SOURCE_USERNAME`、
`XINSHI_MIGRATION_SOURCE_PASSWORD`、`XINSHI_MIGRATION_SOURCE_HOST`、
`XINSHI_MIGRATION_SOURCE_PORT`。每行采用 Docker env-file 的 `名称=值` 格式，
不使用 `export` 或 shell 引号。host 使用来源容器名，port 使用 3306。

站点已有 `settings.php` 会载入 `settings.local.php`。在本地设置中加入：

```php
<?php

// This local setting prevents web requests from starting historical queue work.
$config['automated_cron.settings']['interval'] = 0;

if (PHP_SAPI === 'cli' && getenv('XINSHI_MIGRATION_RUN') === '1') {
  require dirname($app_root) . '/scripts/migration/settings.source.example.php';
}
```

已有本地设置时合并上述片段，保留现有内容且不重复 PHP 起始标记。
来源连接只在显式迁移 CLI 中载入。此文件及来源 env-file 不随 SQL 导出上线。

批次中的 `batch-runtime.json` 使用 `batch-runtime.docker-local.example.json`
作为接入示例。`target_database` 为 `builder`，`environment` 为 `local`；
`accepted_source_exceptions` 在用户批准后记录 S3—S6，原始证据文件保持不变。
`input-manifest.json` 绑定新规则文件的 SHA-256。

## 执行与核验

从主机 `builder-cms` 根目录执行：

```sh
umask 077
bash scripts/migration/docker-local.sh help
export BUILDER_MIGRATION_SOURCE_ENV_FILE='/secure/private/source.env'
bash scripts/migration/docker-local.sh status
```

每次调用只运行一个步骤。第二个参数可指定容器内的私密批次绝对路径，默认是
`/var/www/html/.migration-private/batch`。容器、应用根目录、URI 和 CLI 用户可以
分别通过 `BUILDER_MIGRATION_CONTAINER`、`BUILDER_MIGRATION_APP_ROOT`、
`BUILDER_MIGRATION_ORIGIN`、`BUILDER_MIGRATION_EXEC_USER` 调整。
入口仍会核对实际数据库名称、源/目标 UUID 和输入摘要。

目标完成基线恢复后，保持目标无业务写入。当前容器使用 s6，可先暂停 Web 和定时任务，CLI 仍可运行：

```sh
docker exec builder-pro s6-svc -d /run/service/10-nginx
docker exec builder-pro s6-svc -d /run/service/20-php-fpm
docker exec builder-pro s6-svc -d /run/service/svc-cron
```

暂停期间健康检查失败属于预期。随后按顺序执行迁移步骤：

1. `backup`
2. `install-modules`
3. `adapt-block-body`
4. `install-config`
5. `map-config-users`
6. `validate-model`
7. `clear-generated-rows`
8. `preflight`
9. `import`
10. `verify`
11. `verify-entities`
12. `verify-views`
13. `verify-access`

不要用一个忽略中间错误的循环连续执行。初始化只能针对匹配基线的空目标；
导入前 S3—S6 必须已获批准。失败后的续跑使用本次原批次目录和执行报告。
每个目标有自己的数据库恢复点和执行状态。

`local_generated` 会将本地 OAuth 签名文件创建在批次的 `oauth-local` 目录。
还须检查容器 PHP-FPM 用户对该子目录、父目录和密钥的读取权限，再验收应用登录。
仅为所需运行用户授予访问，其他私密规则、来源环境文件不公开。

本容器 PHP-FPM 为 UID 80 / GID 82。`install-config` 后，以 root 将两个父目录
和 oauth-local 目录、密钥的组设为 82；父目录设 0710，oauth-local 设 0750，
密钥设 0640。输入规则仍为 0600，来源 env-file 留在应用挂载之外。验证时
使用 `docker exec --user 80:82 builder-pro php` 检查两份密钥可读且配对，
不要输出密钥正文。不要仅凭 macOS 共享挂载显示的 mode 判断实际读取隔离。

用户已确认附件保留在线上，本地不下载附件。此次本地验收核对 1,593 条
受管文件记录、Media、所有者及全部所选历史引用；本地 files 缺失不阻塞数据库导出。
文件本体、162 个 WebP 旁文件和 9 个字面路径候选留待线上部署时验收。
可在服务器原 files 目录只读运行以下命令：

```sh
python3 scripts/migration/verify-physical-files.py \
  --root docroot/sites/default/files \
  < .migration-private/batch/online-file-verification-manifest.private.json \
  > .migration-private/physical-files.private.json
```

该核验会计算实际文件摘要和大小；没有来源文件摘要时，不能宣称与线上原文件
逐字节相同。还需验证 PHP-FPM 可读、媒体预览、历史引用及下载。

## 本地通过后导出上线

先完成本次目标的数据、实体修订、Views 和权限验收，保持本地停止写入，
使用配套导出器导出 `builder`。导出器要求五份本轮核验报告全部通过，检查规则
摘要、来源例外批准和目标库身份，再从站点默认连接读取凭据；密码不出现在参数或报告中。

```sh
python3 scripts/migration/export-docker-local.py \
  --output /secure/private/builder-validated.sql.gz
```

导出包含全部表结构、业务数据、配置、历史、Migrate 映射和归属记录，仅省略
cache、sessions、queue、临时令牌等运行表的数据。SQL 不包含 CREATE / DROP
DATABASE 或 USE 指令；压缩文件经完整解压检查、表集合核对并生成 SHA-256 报告。
输出不得覆盖已有文件，失败时保留私密诊断和 partial 文件供检查。

成功导出后恢复本地服务，再检查本地域名和登录页的 HTTP 响应：

```sh
docker exec builder-pro s6-svc -u /run/service/20-php-fpm
docker exec builder-pro s6-svc -u /run/service/10-nginx
docker exec builder-pro s6-svc -u /run/service/svc-cron
```

线上使用配套代码、已经存放在服务器的 files、自己的 `settings.php` / 环境变量
和正式 OAuth 签名文件。`settings.deployment.example.php` 通过
`BUILDER_OAUTH_PRIVATE_KEY` / `BUILDER_OAUTH_PUBLIC_KEY` 覆盖数据库的本地密钥路径，
并验证正式密钥配对且位于 docroot 之外。本地生成的签名密钥不用于生产。
Linux DO 回调、OAuth 客户端回调及站点绝对 URL 按实际线上域名逐项检查，
不要全局替换正文和业务 JSON 中的域名或路径。

线上覆盖前先停写并保存当前线上数据库、文件、密钥及环境设置的恢复点，
再导入已验收的 SQL；随后清理本地运行产生的缓存和会话、核对定时任务与队列，
重建搜索、站点地图及访问派生数据并复验。当前不执行线上覆盖，也不以 SSH 为本地迁移前提。
