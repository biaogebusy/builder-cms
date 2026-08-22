# XINSHI Linux.do

把 [linux.do](https://linux.do) 作为外部身份源(IdP)接入信使的 OAuth 服务器。Angular SPA 走标准 PKCE 流程对 `simple_oauth` 授权，linux.do 是夹在 `/oauth/authorize` 背后的联邦登录入口，前端不感知 linux.do 的存在。

## 一、设计

- **集成模式**：Drupal IdP 联邦 — linux.do access_token 仅 Drupal 端使用，前端永远拿不到
- **用户映射**：按邮箱合并，首次绑定后只认 `linux_do_id`，防账号劫持
- **state**：HMAC 签名的无状态 token（`hash_salt` 为密钥），不依赖 PHP session，避免跨站回跳时 cookie 丢失
- **会话建立**：linux.do 流程结束后 `user_login_finalize` 建立 Drupal session，再回到 `/oauth/authorize` 让 `simple_oauth` 按现有 PKCE 流程发 code
- **依赖**：仅 `simple_oauth`，不依赖 `social_auth` / `openid_connect`

## 二、登录流程

```
Angular socialLogin({idp:'linux_do'})
  → /oauth/authorize?...&idp=linux_do          (simple_oauth)
    → AuthorizeIdpRedirectSubscriber 拦截匿名+idp，302 到
  → /user/login/linux_do?return_to=/oauth/authorize?...
    → 生成签名 state（含 destination），302 到 linux.do
  → linux.do 授权
  → /user/login/linux_do/callback?code&state
    → 校验 state 签名+TTL、换 token、拉 /api/user
    → 邮箱合并/创建用户、写 field_linux_do_id、user_login_finalize
    → 302 回到 destination（原 /oauth/authorize?...）
  → simple_oauth 见已登录 → 发 code → 302 到 Angular redirect_uri?code&state
  → Angular PKCE 换 token，登录完成
```

## 三、安装与配置

### 1. 启用模块

```bash
drush en xinshi_linux_do -y
drush cr
```

安装时会自动给 user 实体加两个字段：

| 字段 | 类型 | 用途 |
| --- | --- | --- |
| `field_linux_do_id` | integer(unsigned, big) | linux.do 用户 ID，绑定后只认这个 |
| `field_linux_do_username` | string(255) | 冗余存 username，便于排查 |

### 2. 在 linux.do 注册应用

访问 [https://connect.linux.do/dash/login](https://connect.linux.do/dash/login) 注册应用：

- **应用主页**：`https://<your-domain>`
- **回调地址**：`https://<your-domain>/user/login/linux_do/callback` — **必须用 HTTPS**，且和站点实际 scheme 一致，否则 token 交换会因 redirect_uri 不匹配被拒
- 拿到 `Client ID` / `Client Secret` 备用

### 3. 配置模块

进 `/admin/xinshi/config/linux_do` 填：

| Key | 默认 | 说明 |
| --- | --- | --- |
| `client_id` | `''` | linux.do 应用 client_id |
| `client_secret` | `''` | linux.do 应用 client_secret |
| `authorize_url` | `https://connect.linux.do/oauth2/authorize` | 浏览器跳转用 |
| `token_url` | `https://connect.linux.do/oauth2/token` | **服务端**调用 |
| `userinfo_url` | `https://connect.linux.do/api/user` | **服务端**调用 |
| `login_activate` | `true` | 关闭后入口路由拒绝访问 |
| `download_avatar` | `false` | true 时把头像下载到 `public://linux_do_avatars/` 并写入 `user_picture` |

> **服务端域名兜底**：如果你的服务器到 `connect.linux.do` 出不去（cURL 28 timeout），把 `token_url`、`userinfo_url` 换成 `connect.linuxdo.org`（同一服务、备用域名）即可。`authorize_url` 保持 `.do` 不变 —— 那是浏览器跳转，由用户网络出口。

### 4. 配置 simple_oauth 权限

`/admin/people/permissions` 给 **Authenticated user**（或你想允许通过 OAuth 拿 code 的角色）勾上：

- ☑ **Grant OAuth2 codes** (`grant simple_oauth codes`)

没这个权限，用户即便登录成功，到 Grant Access 页点「允许」也会被服务端拒成 `The user denied the request`。

drush 等价命令：

```bash
drush role:perm:add authenticated 'grant simple_oauth codes'
drush cr
```

### 5.（可选）跳过 Grant Access 同意页

`/admin/config/services/consumer` 编辑前端 SPA 对应的 consumer，勾上 **Automatic authorization** 保存。

之后用户从 linux.do 回来不再看到 "Grant Access to Client" 页面，直接发 code 给 SPA，体验更顺。**仅对自己控制的可信 consumer 开**。

### 6. scope 与角色映射

`simple_oauth` 默认按 scope 名匹配 Drupal 角色 machine name。前端如果请求 `scope=webmaster`，用户必须有 `webmaster` 角色，否则被拒。

linux.do 新建的用户**默认只有 `authenticated`**。如果业务要求新用户能直接拿 webmaster 等高级 scope，自己在 `LinuxDoSDK::mapProfileToUser` 创建分支里 `$user->addRole('webmaster')` 即可。

## 四、前端配合

后端 `/core/base` 接口的 `socialLogin.providers` 加一项：

```json
{
  "enable": true,
  "label": "Linux.do",
  "svgIcon": "linux_do",
  "idp": "linux_do"
}
```

前端（`xinshi-base`）已经支持 `idp` 字段，会自动走 PKCE + `?idp=linux_do` 联邦流程。

## 五、路由

| 路由 | 路径 | 说明 |
| --- | --- | --- |
| `xinshi_linux_do.settings` | `/admin/xinshi/config/linux_do` | 后台配置 |
| `xinshi_linux_do.user.login` | `/user/login/linux_do` | 入口，302 到 linux.do |
| `xinshi_linux_do.user.callback` | `/user/login/linux_do/callback` | 处理 linux.do 回跳 |

## 六、用户映射策略

```
linux.do profile { id, username, email, avatar_template, ... }
    │
    ├─ 1. 按 linux_do_id 查 user → 命中则直接登录（忽略 email 变化）
    │
    ├─ 2. 按 email 查 user → 命中：
    │      - 已绑定不同 linux_do_id → 拒绝（防劫持）
    │      - 未绑定 → 写入 field_linux_do_id，登录
    │
    └─ 3. 邮箱不存在 → 新建 user（username 冲突自动加随机后缀）
```

## 七、安全要点

- `state` 是用站点 `hash_salt` HMAC 签名的无状态 token（payload 含 nonce、签发时间、destination），callback 时校验签名 + 10 分钟 TTL，不依赖 PHP session
- `destination` 编码进 state token 一起回来，再用 `isSafeAuthorizeDestination` 白名单匹配 `/oauth/authorize`，防开放重定向
- linux.do `access_token` 拉完 profile 即丢弃，**不入库**
- `client_secret` 仅存配置，不下发到前端
- 邮箱已绑定到不同 linux_do_id 时**拒绝**，不重新绑定

## 八、常见问题

**Q: 点 Linux.do 按钮后跳到 `/user/login` 而不是 linux.do？**
A: 检查 `?idp=linux_do` 有没有出现在 `/oauth/authorize` 的 query 里；检查事件订阅器是否注册成功（`drush cr` 清缓存）。

**Q: linux.do 回跳报 `Invalid or expired state`？**
A: token 超过 10 分钟 TTL，或 `hash_salt` 在两次请求之间被改过。重新发起登录即可。

**Q: 回跳报 `Token exchange failed`，日志看到 cURL 28 timeout？**
A: 服务端到 `connect.linux.do` 网络不通。把 `token_url`、`userinfo_url` 换成 `connect.linuxdo.org` 备用域名。

**Q: 回跳报 `Token exchange failed`，日志看到 400 / invalid_grant？**
A: redirect_uri 不一致。确保 linux.do 后台「回调地址」、`$GLOBALS['base_url']` 拼出来的 URL、authorize 时和 token 交换时发出去的 redirect_uri 三者完全一致（含 scheme）。HTTPS 站点必须用 HTTPS 注册回调地址。

**Q: Grant Access 页提示 `The 'grant simple_oauth codes' permission is required`，点允许后报 `The user denied the request`？**
A: 当前用户角色没有 `Grant OAuth2 codes` 权限。给 Authenticated user 勾上即可（见 §三.4）。

**Q: 想完全跳过 Grant Access 同意页？**
A: consumer 上勾 **Automatic authorization**（见 §三.5）。

**Q: 同邮箱想换绑 linux.do 账号？**
A: 手动清空该 user 的 `field_linux_do_id`，下次登录会按邮箱重新绑定。

**Q: 卸载模块要删字段吗？**
A: `hook_uninstall` 会自动删除两个 field storage。

## 九、相关文件

- [docs/develop/linux-do-oauth](https://docs.builder.design/?path=/docs/%E5%BC%80%E5%8F%91-%E6%8E%A5%E5%85%A5-linux-do-%E7%99%BB%E5%BD%95--docs) — 完整接入文档
- `src/LinuxDoSDK.php` — OAuth client + 用户映射 + state 签名
- `src/EventSubscriber/AuthorizeIdpRedirectSubscriber.php` — 联邦关键拦截器
- `src/Controller/LinuxDoAuthController.php` — login / callback 入口
