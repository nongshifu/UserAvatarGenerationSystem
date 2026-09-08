# 对外 API 详细设计

> 对外开发者 API，供第三方通过 KEY 调用。基础路径：`/api/v1`  
> 所有接口返回统一格式，鉴权通过 Header `X-API-Key`。

---

## 一、通用规范

### 1.1 鉴权
```
Header: X-API-Key: ark_xxxxxxxxxxxxxxxxxxxx
```
- Key 失效/禁用 → `401`
- 积分不足 → `403` (code: 4003)
- 超过限流 → `429`

### 1.2 统一响应格式
```json
{
  "code": 0,
  "message": "success",
  "data": {},
  "timestamp": 1788790000
}
```
| code | 说明 |
|------|------|
| 0 | 成功 |
| 4001 | 参数错误 |
| 4002 | 未授权/Key无效 |
| 4003 | 积分不足 |
| 4004 | 资源不存在 |
| 4005 | 频率超限 |
| 4006 | 内容审核未通过 |
| 5000 | 服务器内部错误 |
| 5001 | AI 服务调用失败 |

### 1.3 分页
请求：`?page=1&per_page=20`  
响应 data：
```json
{
  "list": [],
  "pagination": { "total": 100, "page": 1, "per_page": 20, "last_page": 5 }
}
```

---

## 二、头像生成

### 2.1 生成卡通头像
```
POST /api/v1/avatar/generate
```

**请求体**：
```json
{
  "image": "https://example.com/upload.jpg",
  "sub_user_id": "user_123",
  "style_id": 1,
  "color_id": 2,
  "shape_id": 1,
  "prompt_override": "",
  "is_public": true
}
```
| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| image | string | 是 | 原图公网URL（或本地上传接口返回的URL） |
| sub_user_id | string | 否 | 业务侧子用户标识，不传则不绑定子用户 |
| style_id | int | 否 | 风格ID，不传用默认 |
| color_id | int | 否 | 颜色ID |
| shape_id | int | 否 | 形状ID |
| prompt_override | string | 否 | 自定义提示词覆盖 |
| is_public | bool | 否 | 是否入公共池，默认true |

**响应**（异步，先返回任务）：
```json
{
  "code": 0,
  "data": {
    "record_id": 1001,
    "status": "processing",
    "cost_points": 10,
    "remaining_points": 990
  }
}
```
> 说明：生成走异步队列，调用方需轮询或等待回调获取结果。

### 2.2 查询生成结果
```
GET /api/v1/avatar/generate/{record_id}
```
**响应**：
```json
{
  "code": 0,
  "data": {
    "record_id": 1001,
    "status": "success",
    "avatar_id": 5001,
    "url": "https://cdn.example.com/avatars/xxx.png",
    "thumb_url": "https://cdn.example.com/avatars/xxx_thumb.png",
    "cost_points": 10
  }
}
```
status: `processing` / `success` / `failed`

### 2.3 上传图片（获取URL）
```
POST /api/v1/upload
Content-Type: multipart/form-data
```
| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| file | file | 是 | 图片文件，支持 jpg/png/webp，≤10MB |

**响应**：
```json
{
  "code": 0,
  "data": { "url": "https://cdn.example.com/uploads/xxx.jpg" }
}
```

---

## 三、头像获取

### 3.1 从公共池随机获取头像
```
GET /api/v1/avatar/random
```
**Query 参数**：
| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| style_id | int | 否 | 风格筛选 |
| color_id | int | 否 | 颜色筛选 |
| shape_id | int | 否 | 形状筛选 |
| count | int | 否 | 返回数量，默认1，最大20 |

**响应**：
```json
{
  "code": 0,
  "data": {
    "list": [
      { "id": 5001, "url": "https://...", "thumb_url": "https://...", "style_id": 1, "color_id": 2 }
    ]
  }
}
```

### 3.2 获取当前 KEY 下的头像列表
```
GET /api/v1/key/avatars
```
**Query**：`sub_user_id`(可选)、`style_id`、`page`、`per_page`

**响应**：分页头像列表

### 3.3 获取子用户头像历史
```
GET /api/v1/key/sub-users/{sub_user_id}/avatars
```
**响应**：该子用户的所有头像，按时间倒序

### 3.4 获取头像详情
```
GET /api/v1/avatar/{id}
```
> 仅允许查询归属当前 KEY 的头像或公共头像。

---

## 四、KEY 与用户信息

### 4.1 获取当前 KEY 信息
```
GET /api/v1/key/info
```
**响应**：
```json
{
  "code": 0,
  "data": {
    "name": "我的应用",
    "status": 1,
    "rate_limit": 10,
    "daily_limit": 1000,
    "used_today": 15
  }
}
```

### 4.2 获取关联用户积分
```
GET /api/v1/user/points
```
**响应**：
```json
{
  "code": 0,
  "data": { "balance": 990, "total_consume": 10 }
}
```

---

## 五、配置查询

### 5.1 获取风格/颜色/形状配置
```
GET /api/v1/configs
```
**响应**：
```json
{
  "code": 0,
  "data": {
    "styles": [{ "id": 1, "name": "卡通", "icon": "...", "cost_points": 10 }],
    "colors": [{ "id": 1, "name": "暖色", "hex": "#FF6B6B" }],
    "shapes": [{ "id": 1, "name": "圆形", "icon": "..." }]
  }
}
```

---

## 六、错误码与限流

### 6.1 限流规则
- 秒级：按 KEY 的 `rate_limit` 配置，令牌桶算法
- 日级：按 KEY 的 `daily_limit` 配置
- 超限返回 `429` + `Retry-After` 头

### 6.2 积分校验
- 生成前预扣积分，失败退回
- 余额 < 消耗 → `403` code 4003

---

## 七、回调机制（可选）

开发者可在创建 KEY 时配置 `callback_url`，生成完成后由后端 `curl` 发起 POST 回调：
```json
{
  "record_id": 1001,
  "avatar_id": 5001,
  "status": "success",
  "url": "https://...",
  "sign": "hmac_sha256签名"
}
```
签名密钥为 KEY 的 `api_key`，开发者可校验防篡改。

---

> **下一步**：管理后台模块与前端流程设计。
