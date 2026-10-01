# update_to_d11

Drupal 升级期间显式执行的迁移与清理工具。启用模块不会自动迁移内容；
迁移、字段清理和模块卸载须先明确目标、备份与执行范围。

维护说明已集中到 xinshi-docs：

- [迁移工具与旧版兼容模块](../../../../../xinshi-docs/stories/depoly/tools/builder-cms-migration-modules.mdx)
- [新站内容迁移与历史记录](../../../../../xinshi-docs/stories/depoly/tools/builder-cms-content-migration.mdx)
- [CMS 整改进度](../../../../../xinshi-docs/stories/develop/engineering/cms-custom-modules-progress.mdx)

命令入口为 `src/Drush/`。当前依赖以 `update_to_d11.info.yml`、项目
`composer.json` 和 `composer.lock` 为准。旧 README 中的包升级和站点状态
描述仅对应当时升级阶段，可从 Git 历史查阅，不能直接当作当前部署步骤。
