# AI 生成引擎与头像池流程设计

> 核心业务引擎，负责头像生成全流程、三池写入、积分扣减、审核。

---

## 一、生成全流程

```
用户请求生成
    │
    ▼
┌─────────────────┐
│ 1. 参数校验      │  校验图片URL、风格/颜色/形状ID
└────────┬────────┘
         ▼
┌─────────────────┐
│ 2. 积分预扣      │  扣减积分，写入 point_logs(type=consume)
│                 │  余额不足直接返回 403
└────────┬────────┘
         ▼
┌─────────────────┐
│ 3. 创建头像记录  │  avatars 表 status=generating
│                 │  generation_records 表 status=pending
└────────┬────────┘
         ▼
┌─────────────────┐
│ 4. 异步队列任务  │  投递到 Redis Queue
└────────┬────────┘
         ▼ 返回 record_id 给前端

         ─── 队列消费 ───
         ▼
┌─────────────────┐
│ 5. 拼接提示词    │  prompt = 模板.prompt + style.suffix + color.suffix + shape.suffix
└────────┬────────┘
         ▼
┌─────────────────┐
│ 6. 调用豆包API   │  POST /images/generations
│                 │  body: { model, prompt, image, size, watermark... }
└────────┬────────┘
         ▼
    ┌────┴────┐
    │ 成功     │ 失败(重试≤3次)
    ▼         ▼
┌────────┐ ┌──────────┐
│ 7.转存  │ │ 退回积分  │
│ 到自有  │ │ 标记failed│
│ 存储    │ └──────────┘
└───┬────┘
    ▼
┌─────────────────┐
│ 8. AI 审核(开关) │  审核开关开启 → 调内容安全API
└────────┬────────┘
         ▼
    ┌────┴────┐
    │ 通过     │ 驳回
    ▼         ▼
┌────────┐ ┌──────────┐
│ 9.入池  │ │ 退积分    │
│ 三池写入│ │ audit=reject│
└───┬────┘ └──────────┘
    ▼
┌─────────────────┐
│ 10. 更新状态     │  avatars.status=success, audit_status=approved
│                 │  generation_records.status=success
└─────────────────┘
```

---

## 二、三池写入逻辑

| 池 | 判断条件 | 字段 |
|----|----------|------|
| 公共池 | `is_public=true` 且 `audit_status=approved` | `avatars.is_public = 1` |
| KEY 池 | 调用来源有 `key_id` | `avatars.key_id = xxx` |
| 子用户池 | 传了 `sub_user_id` | `avatars.sub_user_id = yyy` |

**一个头像同时满足多条件则同时入多池**。

### 2.1 写入顺序
1. 先写入 `avatars` 主记录（含三池归属字段）
2. 更新/创建 `sub_users` 记录（key_id + identifier）
3. 公共池通过 `is_public` 字段直接查询，无需额外表

---

## 三、积分规则

### 3.1 消耗计算
```
cost = styles.cost_points ?: site_settings.default_cost
```
- 每种风格可单独配置消耗，0 或空则用全局默认
- 生成前预扣，失败/驳回退回

### 3.2 注册赠送
- 新用户注册时写入 `point_logs(type=register, change=register_bonus)`
- `users.points` 同步更新

### 3.3 充值
- 订单支付成功 → 写入 `point_logs(type=recharge)`
- 积分 = `product.points + product.bonus_points`

---

## 四、AI 审核模块（独立开关）

### 4.1 开关配置（site_settings）
| key | 默认 | 说明 |
|-----|------|------|
| audit.enabled | false | 总开关 |
| audit.auto_approve | true | 审核通过自动入公共池 |
| audit.refund_on_reject | true | 驳回是否退积分 |

### 4.2 审核流程
```
生成成功 → 获取结果图
    │
    ▼
audit.enabled = true?
    │
   是 ──→ 调用内容安全API（检测是否为真人头像/违规）
    │         │
    │      通过 ──→ audit_status = approved
    │         │       is_public 按用户选择
    │      驳回 ──→ audit_status = rejected
    │                 refund_on_reject=true → 退积分
    │
   否 ──→ audit_status = approved（直接通过）
```

### 4.3 审核服务（可插拔）
- 接口抽象 `AuditServiceInterface`
- 实现：豆包内容审核 / 阿里云内容安全 / 本地规则
- 后台可切换服务商

---

## 五、豆包 API 调用封装

### 5.1 请求参数
```php
// 自写框架的配置读取：\App\Core\Config::get('api', 'ark_model')
[
    'model' => \App\Core\Config::get('api', 'ark_model'),
    'prompt' => $finalPrompt,
    'image' => $originImageUrl,
    'sequential_image_generation' => 'disabled',
    'response_format' => 'url',
    'size' => \App\Core\Config::get('api', 'image_size'),
    'stream' => false,
    'watermark' => \App\Core\Config::get('api', 'watermark'),
]
```

> 说明：以上代码示例使用自写框架的 `\App\Core\Config` 配置类，不再依赖 Laravel 的 `config()` 助手。配置项来自 `site_settings` 表（或 `backend/config/api.php` 文件）。

### 5.2 响应处理
- 成功：`data[0].url` 为结果图 URL
- 下载结果图 → 上传到自有对象存储 → 保存 `result_url`
- 生成缩略图 → 保存 `result_thumb_url`

### 5.3 异常与重试
- 网络错误/超时：自动重试，最多 3 次，指数退避
- 业务错误（如违规 prompt）：不重试，直接失败退积分
- 重试仍失败：`generation_records.status=failed`，退积分

---

## 六、头像池查询优化

### 6.1 随机头像
- Redis 缓存：每 10 分钟从公共池抽取 200 条 ID 存入 Set
- 随机接口从 Set 中 `SRANDMEMBER`
- 分类筛选：按 `(style_id, color_id)` 分桶缓存

### 6.2 KEY 头像列表
- 索引 `(key_id, status, created_at)`
- 分页查询

---

## 七、回调通知

生成完成后，若 KEY 配置了 `callback_url`：
```php
POST callback_url
{
    "record_id": 1001,
    "avatar_id": 5001,
    "status": "success",
    "url": "https://...",
    "timestamp": 1788790000,
    "sign": "HMAC-SHA256(payload, api_key)"
}
```
开发者用 `api_key` 校验签名防篡改。

---

> **设计文档完成。可进入开发阶段。**
