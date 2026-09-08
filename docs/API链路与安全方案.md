# 开放 API 链路说明与安全方案

> 面向团队成员：按本文档的「文件 + 行号」可快速定位开放 API 从鉴权到生图的完整链路，并据此推进后期安全加固。
> 部署环境：阿里云 ECS + 宝塔 + Nginx + PHP 8.3（FPM）+ MySQL 5.6 + Redis；线上路径 `/www/wwwroot/myradar.cn/`。

---

## 一、系统运行架构

```
开发者服务器 ──HTTPS──> Nginx ──> PHP-FPM（Web 请求：鉴权/扣积分/入队）
                                      │
                                      │ LPUSH（Redis 队列 avatar:generate）
                                      ▼
                              Redis（队列 + 限流计数 + 缓存）
                                      ▲
                                      │ BRPOP（阻塞取任务）
                                      │
 Supervisor 守护 ──> PHP CLI Worker（bin/worker.php）── 调豆包 API 生图 ──> 落库/退款
```

- **Web 层（FPM）**：只做鉴权、校验、预扣积分、投递队列，快速返回 `record_id`。
- **Worker 层（CLI）**：Supervisor 守护 1 个进程常驻，阻塞消费队列，调用豆包图生图接口，成功落库、失败重试/退款。**改后端 PHP 代码后必须重启 Worker**。
- **Redis**：任务队列 + QPS/日限额计数 + 站点缓存。
- **MySQL**：`backend/database/schema.sql` 为唯一权威结构（已合并历史全部迁移）。

---

## 二、API KEY 生命周期

| 环节 | 位置 | 说明 |
|---|---|---|
| 生成 KEY | `backend/app/Services/KeyService.php` `generateApiKey()` L105-109 | `random_bytes(24)` 生成 `ak_` + 48 位 hex |
| 存储 | `KeyService.php` `create()` L35-58 | 明文 KEY **仅创建时返回一次**（L57）；库中 `api_key` 存脱敏串 `ak_xxxx****xxxx`（L44），`api_key_hash` 存 `sha256(明文)`（L42）用于查询 |
| 重置 | `KeyService.php` `reset()` L63-75 | 旧 KEY 立即失效，新明文仅返回一次 |
| 启用/禁用 | `KeyService.php` `toggle()` L80-87 | `status` 0/1，禁用后鉴权直接 401 |
| 用户端页面 | `backend/app/Controllers/Web/KeyController.php` + 控制台「API KEY」菜单 | 普通用户自助管理 |
| 后台管理 | `backend/app/Controllers/Admin/KeyController.php` | 管理员查看/管理全部 KEY |

表结构：`user_keys`（见 `backend/database/schema.sql`），关键字段：
`api_key_hash`（查询用）、`status`（启停）、`rate_limit`（QPS）、`daily_limit`（日限额）、`used_today`（今日已用）、`callback_url`（回调预留）。

---

## 三、一次 API 调用的完整链路

以 `POST /api/v1/avatar/generate`（异步生图）为例：

### 1. 路由与鉴权中间件
`backend/routes/api.php`
- L14-33：`$auth` 中间件。
  - L15：从请求头取 `X-API-Key`（**KEY 放在 header，不进 URL，因此不会出现在 Nginx access log / 浏览器历史**）。
  - L17：缺失返回 `4002 / 401`。
  - L20：调 `ApiAuthService::verify()`。
  - L26：调 `RateLimitService::check()` 限流，超限 `4029 / 429`。
  - L31：把 `{key, user}` 挂到 request，放行。
- L35-47：路由组。注意 `/upload`、`/avatar/random`、`/configs` **不带鉴权**（公开接口）；其余均挂 `[$auth]`。

### 2. KEY 校验
`backend/app/Services/ApiAuthService.php` `verify()` L18-32：
1. L20：长度 < 16 直接拒绝（挡瞎猜的垃圾请求）。
2. L23：`UserKey::findByApiKey()` —— 见 `backend/app/Models/UserKey.php` L20-25：`sha256(传入KEY)` 后按 `api_key_hash` 精确查库。
3. L24：KEY 不存在或 `status≠1`（`isActive()` L27-30）→ 拒绝。
4. L27-28：关联用户不存在或被禁用 → 拒绝。
5. 通过则返回 `['key' => UserKey, 'user' => User]`。

> 现状：**纯「持有即凭证」（Bearer Token）模式，不绑定 IP、不校验签名**。谁拿到明文 KEY 谁就能用。

### 3. 限流
`backend/app/Services/RateLimitService.php`
- `check()` L24-54：
  - 日限额 L31-38：Redis key `rate:daily:{keyId}:{日期}`，≥ `daily_limit` 返回 429。
  - QPS L41-51：Redis key `rate:qps:{keyId}:{秒}` 固定窗口计数，> `rate_limit` 返回 429。
- `consume()` L59-72：生成成功后调用，日计数 +1 并同步 `user_keys.used_today`（L71 `markUsed()`，防 Redis 丢数）。
- `checkIp()` L77-85：按 IP 的限流（前台测试生成等场景用）。

### 4. 业务入口（控制器）
`backend/app/Controllers/Api/GenerateController.php`
- `generate()` L21-77（异步）：
  - L30-37：`image` 参数必填，须为公网 URL 或 `/uploads/...` 路径。
  - L40：取出中间件挂载的 `{key, user}`。
  - L46-49：**强制手机验证**——后台开启 `sms.sms_force_verify` 后，开发者账号 `phone_verified≠1` 直接 403。
  - L52-58：调 `AvatarGenerateService::createTask()`。
  - L60：`RateLimitService::consume()` 计入日限额。
  - L71-76：返回 `record_id / status=processing / 扣减积分 / 剩余积分`。
- `generateSync()` L92-165（同步，multipart 上传即生图）：校验文件 L107-128 → 转存原图 L131-137 → `set_time_limit(120)` L140 → `AvatarGenerateService::generateSync()` L145。
- `query()` L171-203：按 `record_id` 查结果；**L182 校验记录归属**（只能查自己账号的任务），返回头像 URL。

### 5. 任务创建（预扣积分 + 入队）
`backend/app/Services/AvatarGenerateService.php` `createTask()` L37-134：
1. L47-58：上传图片 **AI 预审**（`ImagePrecheckService`，调豆包图像理解模型）；不通过直接拒绝、**不扣积分**；服务异常按后台 `precheck_fail_open` 策略放行或拒绝。
2. L68：`calcCost()` L446-454 算本次积分（付费风格取 `styles.cost_points`，否则取配置 `points.default_cost`）。
3. L71-89：创建 `generation_records` 记录（status=pending，`origin_image_url` 落库——队列丢消息时 Worker 可凭记录重建任务）。
4. L92：`PointService::consume()`（`backend/app/Services/PointService.php` L22）**预扣积分**，余额不足抛异常 → 接口返回 403。
5. L103-120：`RedisClient::push()`（`backend/core/RedisClient.php` L119，LPUSH）投递队列 `avatar:generate`；入队失败抛异常（积分已扣、DB 有记录，可补偿）。

### 6. Worker 消费（调豆包生图）
- 入口：`backend/bin/worker.php`（Supervisor 守护），循环调 `QueueWorker::run()`。
- `backend/app/Jobs/QueueWorker.php`：
  - L28 `run()`：L39 `RedisClient::pop()`（`core/RedisClient.php` L125，**BRPOP 阻塞弹出；phpredis 返回 `['队列名','元素']`，任务 JSON 在 `[1]`，取错会丢任务**）。
  - L44-45：Redis 断开自动重连。
- `AvatarGenerateService::consume()` L193：Worker 取到任务后的处理入口（记录不存在则跳过 L198）。
- `runGeneration()` L256：
  - L269：`ArkApiService::generateImage()`（`backend/app/Services/ArkApiService.php` L20）调豆包；
    - L26：尺寸 `strtolower()`（豆包要求小写 `2k`）；
    - L56：请求头 `Authorization: Bearer {豆包KEY}`（KEY 来自后台「API生图配置」，DB 优先于 `config/api.php`）。
  - 成功：下载结果图、写 `avatars` 表、回填 `generation_records`。
  - L335：若开启结果审核且被驳回，`PointService::refund()` 退积分。
- `handleFailure()` L370：失败重试（L381 重新入队，`retry_count+1`）；超过重试次数 L389 退款。
- 兜底：`backend/bin/repair.php`（宝塔计划任务每 5 分钟跑）修复卡死/pending 超时任务。
  命令：`sudo -u www /www/server/php/83/bin/php /www/wwwroot/myradar.cn/backend/bin/repair.php`

### 链路时序图

```
开发者                Nginx/PHP-FPM              Redis            Worker(CLI)          豆包API
  │  X-API-Key 请求       │                        │                  │                  │
  │─────────────────────>│ 鉴权 verify()          │                  │                  │
  │                      │ 限流 check()           │                  │                  │
  │                      │ 预审/预扣积分          │                  │                  │
  │                      │ LPUSH 任务 ──────────>│                  │                  │
  │<──── record_id ──────│                        │                  │                  │
  │                      │                        │ BRPOP <──────────│                  │
  │                      │                        │── 任务JSON ─────>│ 生图请求 ────────>│
  │                      │                        │                  │<── 结果图 ────────│
  │  GET 查结果 <────────│ 归属校验后返回 URL      │                  │ 落库/退款         │
```

---

## 四、安全现状评估

### KEY 的本质
API KEY 是 **Bearer Token（持有即凭证）**：服务端不校验调用方身份，只校验「这串 KEY 对不对」。**KEY 泄露后，任何人都能以该账号身份调用并消耗积分**，这是开放 API 的通用模型（阿里云/腾讯云/AWS 的 AK 同理），安全靠「降低泄露概率 + 泄露后快速止损」兜底。

### 已具备的防护 ✅
| 防护点 | 位置 |
|---|---|
| 全站 HTTPS，链路抓包看不到 KEY | Nginx 证书 + 全站强制 HTTPS |
| KEY 走 header 不走 URL | `routes/api.php` L15（不进 access log / 历史记录 / Referer） |
| 库中只存 sha256 hash，拖库拿不到明文 | `KeyService.php` L42；`UserKey.php` L22 |
| 明文仅创建/重置时展示一次 | `KeyService.php` L57 |
| KEY 可一键重置、可禁用 | `KeyService.php` L63 / L80 |
| QPS + 日限额双限流 | `RateLimitService.php` L24-54 |
| 强制手机验证（可选） | `Api/GenerateController.php` L46 |
| 查询接口做归属校验 | `Api/GenerateController.php` L182 |
| 结果审核驳回自动退款 | `AvatarGenerateService.php` L335 |

### 泄露面分析（哪些情况真会漏）
| 场景 | 能否拿到 KEY | 应对 |
|---|---|---|
| 公共 WiFi / 运营商 / 路由器抓包 | **不能**（HTTPS 加密整段报文） | 已由 HTTPS 覆盖 |
| 开发者自己机器上 Charles/Fiddler 抓包（装了根证书） | 能 | **无法防**，调用方本地必须持有明文 |
| Nginx / PHP / 代理日志打印了请求头 | 能 | 日志脱敏（方案 P2） |
| KEY 写进前端 JS / 小程序包 / App / GitHub | 能 | 文档强提示（方案 P3）+ IP 白名单兜底 |
| 员工 / 服务器被入侵 | 能 | IP 白名单 + 可吊销 + 用量告警 |

---

## 五、后期安全加固方案（按优先级）

### P0｜IP 白名单（性价比最高，强烈建议先做）
头像 API 是「开发者服务器 → 我们服务器」的固定场景，出口 IP 基本不变。
- **表结构**：`user_keys` 增加 `allowed_ips` varchar(500)（逗号分隔，空=不限制）。
- **校验位置**：`ApiAuthService::verify()` L24 之后，取 `$req->ip()`（或 `X-Forwarded-For` 首段，注意 Nginx 配置可信代理）与白名单比对，不在名单返回 403。
- **配置入口**：控制台「API KEY」页（`Web/KeyController` + 对应视图）加文本框；后台 `Admin/KeyController` 同步。
- 效果：KEY 泄露后对方 IP 不在白名单，直接无法调用。

### P1｜调用日志与异常用量告警
- 新建 `api_call_logs`（key_id / ip / 接口 / 状态码 / 耗时 / 时间），或先用 Redis 按 KEY+IP 聚合日统计。
- 告警规则：日用量超日限额 50%、出现从未见过的 IP 段、短时间大量 401（疑似撞 KEY）。
- 通知渠道复用现有 `MailService`（后台「邮箱配置」）/ 短信（`SmsService`）。
- 数据来源位置：鉴权中间件 `routes/api.php` L14-33 可统一埋点。

### P1｜管理员强制吊销 KEY ✅ 已具备
`backend/app/Controllers/Admin/KeyController.php` 已有 `reset()` L70（重置任意 KEY）与 `toggle()` L87（启用/禁用），后台「用户管理/KEY 管理」可操作。接到泄露反馈后应**立即禁用 → 再重置**，30 秒内止损。

### P2｜HMAC 签名模式（防重放 + 防日志直接盗用，专业版可选）
参考阿里云签名机制，给开发者发放 AK + SK 双凭证：
- 请求头加 `X-Timestamp`、`X-Nonce`、`X-Signature = HMAC-SHA256(SK, method+path+timestamp+nonce+body)`。
- 服务端校验：时间戳偏差 ±5 分钟（`ApiAuthService::verify` 内）、nonce 写 Redis 去重（TTL 5 分钟，防重放）。
- **注意边界**：签名防的是「重放攻击」和「日志里的明文 KEY 被直接拿去用」，**防不住 SK 本身泄露**（SK 和 KEY 一样存在调用方机器上）。所以 IP 白名单仍应优先做。

### P2｜日志脱敏
- Nginx `log_format` 中不记录 `X-API-Key` 头（默认不记录自定义头，确认无 `$http_x_api_key` 即可）。
- PHP 侧 `Log::info/error` 不打印完整请求头与 KEY（排查时打脱敏串 `ak_xxxx****`）。

### P3｜文档与产品侧
- 在 `/docs` API 文档页顶部加「安全须知」：KEY 只允许放服务端环境变量/配置文件，**严禁**写进前端 JS、小程序、App 包、公开代码仓库；泄露后立即在控制台重置。
- KEY 增加可选 `expires_at`（过期时间）与 `scope`（权限范围，如只读/仅生成），进一步缩小爆炸半径。

---

## 六、常见问题排查索引

| 现象 | 先看哪里 |
|---|---|
| 调用返回 401 Invalid/Missing API Key | `routes/api.php` L15-23；`ApiAuthService.php` L20-31；KEY 是否被禁用/重置 |
| 返回 429 限流 | `RateLimitService.php` L31-51；`user_keys.rate_limit / daily_limit` |
| 返回 403 手机验证 | `Api/GenerateController.php` L46-49；后台「短信配置 → 强制手机验证」 |
| 返回 processing 后一直不出图 | Worker 是否在跑（Supervisor）；`QueueWorker.php` L39；`bin/repair.php` 是否定时执行；Redis 是否被清空 |
| 豆包生图失败 / 图地址 localhost | `ArkApiService.php` L26；`SeoService::siteBaseUrl()`（后台「站点设置 → 站点 URL」必填公网域名） |
| 积分扣了但失败没退 | `AvatarGenerateService.php` `handleFailure()` L370-389；`PointService::refund()` L49 |
| 改了配置/代码不生效 | 配置：后台保存后 FPM 即生效，**Worker 需重启**；代码：OPcache + 重启 Worker |

---

## 七、关键文件清单

| 文件 | 职责 |
|---|---|
| `backend/routes/api.php` | 对外 API 路由 + 鉴权/限流中间件 |
| `backend/app/Services/ApiAuthService.php` | X-API-Key 校验 |
| `backend/app/Services/KeyService.php` | KEY 生成/重置/启停（hash 存储） |
| `backend/app/Models/UserKey.php` | KEY 模型（按 hash 查询、状态、用量） |
| `backend/app/Services/RateLimitService.php` | QPS / 日限额 / IP 限流 |
| `backend/app/Controllers/Api/GenerateController.php` | 生图接口（异步/同步/查询） |
| `backend/app/Services/AvatarGenerateService.php` | 任务创建、预审、扣积分、入队、Worker 消费、失败退款 |
| `backend/app/Services/ArkApiService.php` | 豆包图生图接口封装 |
| `backend/app/Services/PointService.php` | 积分扣减/退还 |
| `backend/app/Jobs/QueueWorker.php` | 队列消费循环 |
| `backend/core/RedisClient.php` | Redis 封装（push L119 / pop L125） |
| `backend/bin/worker.php` | Worker 启动入口（Supervisor 守护） |
| `backend/bin/repair.php` | 卡死任务修复（计划任务 5 分钟） |
| `backend/database/schema.sql` | 数据库唯一权威结构 |
