<h1 align="center">信使 Web builder 低代码 CMS</h1>

<p align="center">
  <i>Builder CMS 是基于 Drupal 的 Headless 内容管理系统，是信使 Web builder 的后台服务。Web builder 是基于 Material 的 Angular 低代码前端框架，通过拖拽可视化配置快速构建现代化响应式 UI，支持多主题、多语言。<br>深度集成 AI：支持对话、文案优化、图表生成、创建组件、一句话生成整页，并内置 AI Harness（可接入多模型、统一评测与用量管控）。
    </i>
  <br>
</p>

<p align="center">
  <a href="https://base.builder.design"><strong>https://base.builder.design</strong></a>
  <br>
</p>

<p align="center">
  <a href="https://base.builder.design/builder">Web Builder </a>
  ·
  <a href="https://github.com/biaogebusy/xinshi-mini">信使小程序</a>
  ·
  <a href="https://ui.builder.design"> 技术文档 </a>
  ·
  <a href="https://www.zhihu.com/people/biaogebusy"> 知乎 </a>
  <br>
  <br>
  <a href="https://www.bilibili.com/video/BV1ux4y197kc/?vd_source=f65b4e2d70ecc450290b6b1710c0ada5#reply998790468">观看演示视频</a>
</p>

> 奥陌陌是已知的第一颗经过太阳系的星际天体，意为"远方信使"。

## web builder

| 功能点               | 说明                                                                  |
| -------------------- | --------------------------------------------------------------------- |
| AI (Pro 版)          | 基于 DeepSeek，基础对话、优化文案、生成图表、创建组件和一句话生成页面 |
| Layout builder       | 动态 layout，基于 TailwindCss 的动态组件，支持静态数据和 API 数据来源 |
| 组件编辑             | 删除、复制 JSON、编辑组件数据、拖动上下排列                           |
| 媒体库               | 可在前台批量上传、查看、更新媒体库                                    |
| 小程序数据维护       | 通过 builder 管理小程序的页面和组件                                   |
| 页面历史版本         | 当提交、清空、加载示例等覆盖操作时新增历史版本                        |
| 草稿检测             | 当前内容有最新时，提醒是否拉取最新                                    |
| 丰富的模板库、示例库 | 基于模板库选择模板，快速生成页面                                      |
| 复制整个页面的 JSON  | 可直接复制 json，部署到后台发布                                       |
| 动画                 | 支持通用的 AOS 页面滚动动画和高细粒度的 GSAP 动画                     |
| 切换全宽             | 方便大屏编辑，减少干扰                                                |
| 快速生成页面         | 根据一定的规则从组件库中生成页面                                      |
| 多主题切换预览       | 预览在多主题下的组件显示情况                                          |
| 暗黑风格             | 支持切换浅色风格和暗黑风格，专注内容创作                              |
| 页面预览             | 调转到新窗口查看真实的页面                                            |
| 响应式预览           | 可切换不同设备尺寸查看页面响应式排版                                  |

> 基于 DeepSeek AI 生成 UI 组件、图表、文案已经开发测试当中，敬请期待。

## Web builder 管理微信小程序的页面和组件

<p align="center">
  <i>信使小程序是基于 Taro+NutUI 的小程序，Vue3拥有更好的编辑开发体验，<br>在<strong>Web builder</strong>中通过拖拽可视化配置，小程序可动态构建页面。
    </i>
  <br>
  <a href="https://github.com/biaogebusy/xinshi-mini">Github 开源地址</a>
</p>

## 相关视频演示

- [AI 创建自定义组件，优化文案，一句话生成整个页面](https://www.bilibili.com/video/BV17HfhYKEMz/)
- [构建 Drupal CMS 预览版宣传着陆页](https://www.bilibili.com/video/BV1mD6hY6Et3/)
- [支持 AOS 通用页面滚动动画](https://www.bilibili.com/video/BV1VKkSY3E1r/)
- [Layout 嵌套](https://www.bilibili.com/video/BV1pH4y1L7qk)
- [API 数据来源](https://www.bilibili.com/video/BV1ux4y1n7MA/) | [静态数据来源](https://www.bilibili.com/video/BV1rf421R7He/) TailwindCss 自定义组件
- [复制功能快速构建组件](https://www.bilibili.com/video/BV1nF4m1w7cs/)
- [多语言发布及媒体库管理](https://www.bilibili.com/video/BV1XohfeoEtz/)
- [自定义示例和模板库](https://www.bilibili.com/video/BV1wExPeBEdn)

## 本地开发环境(base / pro)

CMS 分为 base 基础版和 Pro 专业版，两者使用**同一份程序代码**，通过 Docker 编排同时运行，差异如下:

| 版本 | Web 容器 | 数据库 | 配置导出目录 | 本地域名 |
| ---- | -------- | ------ | ------------ | -------- |
| base | `builder-base` | `drupal_base` | `config/base/sync` | `builder-cms-base.docker.localhost` |
| Pro  | `builder-pro`  | `drupal_pro`  | `config/pro/sync`  | `builder-cms-pro.docker.localhost` |

两版通过容器环境变量(`DB_HOST`、`DB_NAME`、`CONFIG_SYNC_DIRECTORY` 等)区分数据库与配置目录，`docroot/sites/default/settings.php` 优先读取环境变量，未设置时保持原有单容器行为。

### 启动步骤

```bash
# 1. 创建外部网络(仅首次,已存在则跳过)
docker network create app_net

# 2. 配置本机 DNS,让 *.docker.localhost 解析到本机(仅首次,需 sudo)
sudo sh -c 'echo "127.0.0.1 builder-cms-base.docker.localhost builder-cms-pro.docker.localhost" >> /etc/hosts'

# 3. 启动服务
cd docker
docker-compose up -d
```

启动后访问:

- base:http://builder-cms-base.docker.localhost
- Pro:http://builder-cms-pro.docker.localhost

### 首次初始化

两个 MySQL 容器各自初始化**空库**，首次需分别安装站点或导入已有数据库:

```bash
docker-compose exec builder-base drush site:install --account-pass=admin -y
docker-compose exec builder-pro drush site:install --account-pass=admin -y
```

### 配置管理

两版启用的模块不同(如 Pro 版多启用 xinshi_ai 等模块)，配置各自导出，互不覆盖:

```bash
docker-compose exec builder-base drush cex -y   # 导出到 config/base/sync
docker-compose exec builder-pro drush cex -y    # 导出到 config/pro/sync
```

> `sites/default/files` 为两版共享，本地上传的文件互通;两版可独立启停，如只跑 Pro:`docker-compose up -d builder-pro mysql-pro`。

## 最后

- QQ 交流群：1176468251
- 如果觉得这个项目对您有所助益，请帮忙点个 star

[![Star History Chart](https://api.star-history.com/svg?repos=biaogebusy/builder-cms&type=Date)](https://star-history.com/#biaogebusy/builder-cms&Date)
