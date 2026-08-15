# 模型注册中心 API — 前端对接文档

> 模块:`xinshi_ai` · 适用分支:`10.x` · 更新:2026-08-09
> 后端实现:`src/Controller/ModelRegistryController.php`(公开读)、`src/Controller/ModelManageController.php`(管理写)

模型注册中心是 AI 任务可用「平台 + 模型」的单一事实源(后端配置 `xinshi_ai.models`)。本文档覆盖:

1. **公开读接口** — 业务前台拉取可用模型(选模型下拉、能力过滤);
2. **管理接口** — 管理前台**新增模型、编辑、启用 / 禁用**(原来只能在 Drupal 后台操作,现已开放 API)。

## 1. 接口总览

| 方法 | 路径 | 鉴权 | 用途 |
| --- | --- | --- | --- |
| GET | `/api/v3/ai/models` | 公开(`access content`) | 可用模型列表(**不含禁用条目**),支持 `?capability=` 过滤 |
| GET | `/api/v3/ai/manage/models` | Bearer / Cookie + `administer xinshi_ai` | 管理端全量列表(**含禁用的平台与模型**) |
| POST | `/api/v3/ai/models` | 同上 | 新增模型 |
| PATCH | `/api/v3/ai/models/{id}` | 同上 | 部分更新;`{"enabled": false}` 即禁用 |
| PATCH | `/api/v3/ai/manage/defaults` | 同上 | 设置 / 清除各模式默认模型(§4.5) |

没有 DELETE 接口:下线模型用「禁用」;物理删除走 Drupal 后台(`/admin/config/xinshi/ai/models`)。平台(`xinshi` / `custom`)是固定基建,不能通过 API 增删启停。

## 2. 鉴权

- 管理接口(表中后三个)要求登录用户具有 **`administer xinshi_ai`** 权限。
- SPA 推荐走 OAuth2:`Authorization: Bearer <access_token>`(与 `/api/v3/image-jobs` 相同的令牌与拦截器即可);同域 Cookie 会话亦可。
- 写请求需带 `Content-Type: application/json`,body 为 JSON。
- 未认证 → `401`;已认证但无权限 → `403`(响应体为 Drupal 标准 `{"message": "..."}`)。

## 3. 数据结构

```ts
export type AiCapability = 'chat' | 'reasoning' | 'image' | 'image-edit';

/** 模型条目(注册中心存储的即此结构,采用「稀疏键」约定,见下)。 */
export interface AiModel {
  id: string;                    // 上游真实模型 ID,如 gpt-image-2;创建后不可改
  label: string;                 // 显示名称
  platform: string;              // 所属平台 id:'xinshi' | 'custom'
  capabilities: AiCapability[];  // 能力标签,非空
  max_n?: number;                // 单次最大生成张数(图像模型),≥1
  sizes?: string[];              // 支持尺寸,如 ['1024x1024', '1536x1024']
  deprecated?: boolean;          // 仅为 true 时出现:已弃用(仍可用,软提示)
  enabled?: boolean;             // 仅为 false 时出现:已禁用;键缺省 = 启用
  notes?: string;                // 备注
}

/** 公开接口的平台条目(已裁剪)。 */
export interface AiPlatformPublic {
  id: string;
  label: string;
  user_supplied?: boolean;       // true = custom 平台(用户自带 endpoint/key/model)
}

/** 管理接口的平台条目(原始存储,含内部字段)。 */
export interface AiPlatformRaw {
  id: string;
  label: string;
  description?: string;
  enabled: boolean;
  auth: string;                  // 'gateway' | 'api_key'
  user_supplied?: boolean;
}

export interface AiRegistry<P = AiPlatformPublic> {
  version: string;               // 内容 hash,注册中心任何变更后都会变化,可做前端缓存指纹
  platforms: P[];
  models: AiModel[];
  defaults?: AiModelDefaults;    // 各模式默认模型;键可能整体缺失,前端必须本地兜底
}

/** 各模式默认模型 id(稀疏:未设置的模式无键)。 */
export interface AiModelDefaults {
  chat?: string;
  image?: string;
  'image-edit'?: string;
}
```

**稀疏键约定(重要)**:布尔标记只在「非默认值」时才出现在数据里——`enabled` 只在禁用时出现且为 `false`,`deprecated` 只在弃用时出现且为 `true`。前端判断请统一用:

```ts
const isEnabled = (m: AiModel) => m.enabled !== false;
const isDeprecated = (m: AiModel) => m.deprecated === true;
```

## 4. 接口明细

### 4.1 GET `/api/v3/ai/models` — 公开模型列表

- Query:`capability`(可选)按能力过滤,如 `?capability=image`。
- 禁用的模型、`enabled: false` 的平台**不会返回**。
- 响应可被 HTTP 缓存;后台任何模型变更会自动失效。`version` 变化即数据有变。

```json
{
  "version": "3f2a9c81d0b4",
  "platforms": [
    { "id": "xinshi", "label": "信使" },
    { "id": "custom", "label": "自定义供应商", "user_supplied": true }
  ],
  "models": [
    { "id": "deepseek-chat", "label": "DeepSeek Chat", "platform": "xinshi", "capabilities": ["chat"] },
    { "id": "gpt-image-2", "label": "Gpt image 2", "platform": "xinshi",
      "capabilities": ["image", "image-edit"], "max_n": 1,
      "sizes": ["1024x1024", "1024x1536", "1536x1024"],
      "notes": "Single image only; supports global scripts & legible text-in-image." }
  ]
}
```

### 4.2 GET `/api/v3/ai/manage/models` — 管理端全量列表

管理界面渲染用。与 4.1 的区别:含禁用的模型与平台、平台为原始条目(`AiPlatformRaw`)、响应不缓存(`no_cache`)。

```json
{
  "version": "3f2a9c81d0b4",
  "platforms": [
    { "id": "xinshi", "label": "信使", "description": "信使统一网关…", "enabled": true, "auth": "gateway" },
    { "id": "custom", "label": "自定义供应商", "description": "…", "enabled": true, "auth": "api_key", "user_supplied": true }
  ],
  "models": [
    { "id": "deepseek-chat", "label": "DeepSeek Chat", "platform": "xinshi", "capabilities": ["chat"] },
    { "id": "old-model", "label": "Old Model", "platform": "xinshi", "capabilities": ["chat"], "enabled": false }
  ]
}
```

### 4.3 POST `/api/v3/ai/models` — 新增模型

请求体字段:

| 字段 | 类型 | 必填 | 校验 | 说明 |
| --- | --- | --- | --- | --- |
| `id` | string | ✓ | `^[a-z0-9_.\-]+$`,≤128 | 上游真实模型 ID;创建后不可改 |
| `label` | string | ✓ | 非空,≤255 | 显示名称 |
| `platform` | string | ✓ | 必须是已注册平台 id | 现为 `xinshi` / `custom` |
| `capabilities` | string[] | ✓ | 非空;取值限 `chat` `reasoning` `image` `image-edit` | 能力标签 |
| `max_n` | int | – | ≥1 | 图像模型单次最大张数;省略 = 不限制 |
| `sizes` | string[] | – | 非空字符串数组 | 如 `["1024x1024"]` |
| `deprecated` | bool | – | – | 弃用标记 |
| `enabled` | bool | – | – | 传 `false` 可「创建即禁用」;缺省启用 |
| `notes` | string | – | – | 备注 |

```http
POST /api/v3/ai/models
Authorization: Bearer <token>
Content-Type: application/json

{
  "id": "qwen3-max",
  "label": "Qwen3 Max",
  "platform": "xinshi",
  "capabilities": ["chat", "reasoning"]
}
```

成功 `201`,返回**归一化后已落库的条目**(请用它刷新本地状态):

```json
{ "model": { "id": "qwen3-max", "label": "Qwen3 Max", "platform": "xinshi", "capabilities": ["chat", "reasoning"] } }
```

失败:`409` id 已存在;`422` 校验失败(见 §5)。

### 4.4 PATCH `/api/v3/ai/models/{id}` — 部分更新 / 启停

- **部分更新**:只提交要改的字段,未提交的沿用现值;数组字段(`capabilities` / `sizes`)是**整组替换**,不做逐项合并。
- `id` 不可改,body 里带了不同的 `id` 会 `400`。
- 必填字段(`label` / `platform` / `capabilities`)不可清成空,否则 `422`。
- 可选字段清空 / 复位方式:

| 目的 | 传值 |
| --- | --- |
| 禁用 | `{"enabled": false}` |
| 恢复启用 | `{"enabled": true}`(或 `null`) |
| 取消弃用 | `{"deprecated": false}` |
| 清除 `max_n` | `{"max_n": null}` |
| 清除 `sizes` | `{"sizes": null}` 或 `[]` |
| 清除 `notes` | `{"notes": null}` 或 `""` |

```http
PATCH /api/v3/ai/models/qwen3-max
Authorization: Bearer <token>
Content-Type: application/json

{ "enabled": false }
```

成功 `200`,同样返回 `{ "model": {…} }`(此例里条目会带上 `"enabled": false`)。失败:`404` 模型不存在;`400` / `422` 见 §5。

### 4.5 PATCH `/api/v3/ai/manage/defaults` — 设置各模式默认模型

- **部分更新**:body 只提交要改的模式键,取值限 `chat` / `image` / `image-edit`;值传 `null`(或 `""`)清除该模式的默认。
- 指向的模型必须**已启用**(平台与模型均启用)且**具备与模式同名的能力**,否则 `422 {"errors": {"<mode>": "..."}}`。
- 公开接口(4.1)只在存在有效默认时携带 `defaults` 键,且逐条过滤:默认模型被禁用 / 删除后自动从公开响应消失(**不报错**),前端回落到本地兜底默认。管理接口(4.2)始终返回原始 `defaults`(可能为 `{}`)。

```http
PATCH /api/v3/ai/manage/defaults
Authorization: Bearer <token>
Content-Type: application/json

{ "chat": "deepseek-v4-pro", "image-edit": null }
```

成功 `200`,返回归一化后的完整映射:

```json
{ "defaults": { "chat": "deepseek-v4-pro", "image": "qwen-image-plus" } }
```

## 5. 错误响应汇总

| 状态码 | 场景 | 响应体 |
| --- | --- | --- |
| 400 | body 不是合法 JSON | `{"error": "Invalid JSON body."}` |
| 400 | PATCH 试图改 id | `{"error": "Model id cannot be changed."}` |
| 401 / 403 | 未认证 / 无 `administer xinshi_ai` 权限 | Drupal 标准 `{"message": "..."}` |
| 404 | PATCH 的 id 不存在 | `{"error": "Model not found."}` |
| 409 | POST 的 id 已存在 | `{"error": "Model 'xxx' already exists."}` |
| 422 | 字段校验失败 | `{"errors": {"<字段>": "<原因>"}}`,可直接映射到表单项 |

`422.errors` 的键与文案(英文,前端可按键名本地化):

| 键 | 文案 |
| --- | --- |
| `id` | `id is required and may only contain a-z 0-9 _ . - (max 128 chars).` |
| `label` | `label is required (max 255 chars).` |
| `platform` | `platform must be one of: xinshi, custom.` |
| `capabilities` | `capabilities must be a non-empty array of: chat, reasoning, image, image-edit.` |
| `max_n` | `max_n must be a positive integer.` |
| `sizes` | `sizes must be an array of strings, e.g. ["1024x1024"].` |

## 6. 启用 / 禁用 / 弃用的语义

| 状态 | 公开列表(4.1) | 提交任务(`POST /api/v3/image-jobs`) | 说明 |
| --- | --- | --- | --- |
| 启用(默认) | 返回 | 正常 | — |
| `deprecated: true` | **仍返回**(带标记) | **仍可用** | 软提示,前端自行决定是否降权展示 |
| `enabled: false` | 不返回 | 校验拒绝,`422 {"errors": {"model": "Model 'xxx' does not support …"}}` | 记录保留,可随时重新启用 |

注意:禁用只拦「新提交」——**已入队 / 执行中的任务不受影响**(校验发生在提交时,执行阶段按任务节点里固化的参数走)。

## 7. curl 联调示例

```bash
BASE="https://builder.docker.localhost"
TOKEN="<administer xinshi_ai 权限用户的 access_token>"

# 公开列表(按能力过滤)
curl -s "$BASE/api/v3/ai/models?capability=image"

# 管理端全量
curl -s -H "Authorization: Bearer $TOKEN" "$BASE/api/v3/ai/manage/models"

# 新增
curl -s -X POST "$BASE/api/v3/ai/models" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"id":"qwen3-max","label":"Qwen3 Max","platform":"xinshi","capabilities":["chat"]}'

# 禁用 / 启用
curl -s -X PATCH "$BASE/api/v3/ai/models/qwen3-max" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"enabled":false}'
curl -s -X PATCH "$BASE/api/v3/ai/models/qwen3-max" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"enabled":true}'
```

## 8. Angular 参考实现

```ts
@Injectable({ providedIn: 'root' })
export class AiModelAdminService {
  private readonly base = '/api/v3/ai';

  constructor(private http: HttpClient) {}   // Bearer 由现有 OAuth2 拦截器附加

  listAll() {
    return this.http.get<AiRegistry<AiPlatformRaw>>(`${this.base}/manage/models`);
  }

  create(model: AiModel) {
    return this.http.post<{ model: AiModel }>(`${this.base}/models`, model);
  }

  update(id: string, patch: Partial<AiModel>) {
    return this.http.patch<{ model: AiModel }>(`${this.base}/models/${encodeURIComponent(id)}`, patch);
  }

  setEnabled(id: string, enabled: boolean) {
    return this.update(id, { enabled });
  }
}
```

管理界面建议流程:`listAll()` 渲染表格(开关状态 = `m.enabled !== false`)→ 切开关调 `setEnabled()`、表单提交调 `create()` / `update()` → 用响应里的 `model` 回填该行;`422` 时把 `errors` 按键映射到表单控件。

## 9. FAQ

- **新增后公开列表多久生效?** 即时。写操作保存配置的同时会失效相关缓存,下一次 `GET /api/v3/ai/models` 即为新数据。
- **`version` 有什么用?** 注册中心内容 hash。前端可缓存列表,`version` 不变即可复用;它不用于并发控制(接口无乐观锁,后改的覆盖先改的)。
- **能改模型 id 吗?** API 不允许(避免与历史任务 `field_model` 断链)。确需改名:新建新 id → 禁用旧 id。
- **custom 平台的模型要注册吗?** 不用。`custom` 是用户在前台自带 endpoint / key / model 的通道,提交任务时不查注册中心。
- **默认模型指向的模型被禁用 / 删除了会怎样?** 公开接口(4.1)自动不再携带该模式的 `defaults` 条目,前端回落到本地兜底默认;管理接口仍返回原始映射,便于管理界面发现并修正。换默认前无需先清旧值,直接 PATCH 覆盖即可。
