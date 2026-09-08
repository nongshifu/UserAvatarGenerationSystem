# AI 头像生成系统（User Avatar Generation System）

上传一张真人照片，AI 一键生成卡通 / Q版 / 赛博朋克 / 油画 / 水彩 / 像素风个性头像。
包含**官网展示、用户控制台、对外开放 API、后台管理**四端，基于豆包（火山方舟）Seedream 图像模型。

> 原生 PHP 自研 MVC（无第三方框架）+ MySQL + Redis 队列异步生图，可直接在宝塔面板部署。

---

## ✨ 功能特性

**官网（前台）**
- 首页 Hero、头像池瀑布流、风格分类页、头像详情页（SEO 友好：sitemap / OG 标签 / 独立 TDK）
- 注册登录（邮箱 / 手机验证码找回密码）、深色磨砂玻璃 + 紫青渐变 UI、移动端自适应

**用户控制台 `/console`**
- 在线生成头像（上传照片 → 选风格/颜色/形状 → AI 生成 → 入个人头像库）
- 积分体系：注册赠送、生成消耗、充值套餐、积分流水、订单记录
- **开发者 API KEY 管理**：创建 / 重置 / 启停，明文仅展示一次
- 上传图片 AI 预审（不合规/非人物照片直接拒绝，不扣积分）

**对外开放 API `/api/v1`**
- `X-API-Key` 鉴权，支持异步（提交→轮询）与同步（上传即生图）两种模式
- QPS + 日限额双限流、失败自动重试与积分退还
- 支持子用户标识（sub_user_id），便于开发者按终端用户隔离头像

**后台管理 `/admin/`**
- 数据概览、用户管理、生成记录、头像池审核、提示词管理、积分套餐/流水/订单
- 独立设置模块：**站点设置 / 积分配置 / API 生图 / 审核设置 / 邮箱配置 / 短信配置**
- 多标签页 SPA，深色风格与前台统一

---

## 🧱 技术栈

| 层 | 技术 |
|---|---|
| 后端 | 原生 PHP 8.2+（自研轻量 MVC：Router / Request / Response / Model / DB） |
| 数据库 | MySQL 5.6+（utf8mb4），结构见 [`backend/database/schema.sql`](backend/database/schema.sql) |
| 队列/缓存 | Redis（任务队列 LPUSH/BRPOP + 限流计数 + 站点缓存） |
| 生图 | 豆包（火山方舟）Seedream 图生图 + 视觉模型图片预审 |
| 后台 | 原生 HTML/JS 单页应用（多标签 hash 路由，无构建依赖） |
| 部署 | 宝塔面板 + Nginx + PHP-FPM + Supervisor 守护 Worker |

---

## 📁 目录结构

```
├── backend/                # 后端主程序（网站运行目录指向 backend/public）
│   ├── app/
│   │   ├── Controllers/
│   │   │   ├── Web/        # 官网 + 用户控制台
│   │   │   ├── Api/        # 对外开放 API（/api/v1，X-API-Key 鉴权）
│   │   │   └── Admin/      # 后台管理接口（/admin-api）
│   │   ├── Services/       # 业务服务（生图/鉴权/积分/限流/短信/邮件…）
│   │   ├── Models/         # 数据模型
│   │   ├── Jobs/           # 队列消费 Worker 逻辑
│   │   └── Views/          # 前台视图（含 _partials 公共头尾）
│   ├── core/               # 框架核心（Router/DB/RedisClient/Request…）
│   ├── config/             # 配置文件（database.php 不入库，见 .example）
│   ├── routes/             # 路由定义（web.php / api.php / admin.php）
│   ├── bin/                # worker.php 队列消费；repair.php 卡死任务修复
│   ├── database/schema.sql # ★ 数据库唯一权威结构（全新部署导入此文件）
│   └── public/             # Web 入口 index.php
├── admin/                  # 管理后台 SPA（index.php + assets/）
├── frontend/               # 官网静态资源（js/css）
├── storage/                # 运行时：uploads 上传与生成图 / logs / cache（需可写）
├── deploy/                 # 部署文档、Nginx 伪静态规则
└── docs/                   # 设计与链路文档
```

---

## 🚀 快速部署（宝塔面板）

详细图文步骤见 **[deploy/宝塔部署步骤.md](deploy/宝塔部署步骤.md)**，核心流程：

1. **环境**：Nginx + MySQL 5.6 + PHP 8.2/8.3（扩展：redis、fileinfo、curl、gd、mbstring）+ Redis + 进程守护管理器
2. **传代码**到 `/www/wwwroot/你的站点/`，站点运行目录设为 `/backend/public`，伪静态粘贴 [`deploy/宝塔伪静态.conf`](deploy/宝塔伪静态.conf)
3. **建库导表**：
   ```bash
   mysql -u<user> -p avatar < backend/database/schema.sql
   ```
4. **改配置**：复制 `backend/config/database.example.php` 为 `database.php` 填数据库密码；Redis 密码填 `config/redis.php`；PHP-FPM 环境变量注入 `APP_JWT_SECRET`（`openssl rand -hex 32` 生成）
5. **配豆包 Key**：后台「API 生图」菜单填入（或改 `backend/config/api.php`）
6. **启动 Worker**（Supervisor 守护，否则任务不执行）：
   ```bash
   /www/server/php/83/bin/php /www/wwwroot/myradar.cn/backend/bin/worker.php avatar:generate --timeout=30 --max=100000
   ```
7. **计划任务**（每 5 分钟，修复卡死任务）：
   ```bash
   sudo -u www /www/server/php/83/bin/php /www/wwwroot/myradar.cn/backend/bin/repair.php
   ```
8. **权限**：`storage/` 目录属主 www、可写

> 默认管理员：**admin / admin123**（首次登录立即修改）。

---

## 🔌 开放 API 快速上手

1. 用户注册登录 → 控制台「API KEY」创建 KEY（`ak_` 开头，**明文只展示一次，请妥善保存**）
2. 请求头携带 `X-API-Key: ak_xxxxxx` 调用：

```bash
# 异步生成（推荐）：提交任务拿 record_id，再轮询结果
curl -X POST https://你的域名/api/v1/avatar/generate \
  -H "X-API-Key: ak_xxxxxx" -H "Content-Type: application/json" \
  -d '{"image":"https://example.com/photo.jpg","style_id":1,"is_public":0}'

# 查询结果
curl https://你的域名/api/v1/avatar/generate/{record_id} -H "X-API-Key: ak_xxxxxx"
```

完整接口列表见 [`docs/API设计.md`](docs/API设计.md)，在线文档页 `/docs`。

### 🔑 KEY 安全须知
- KEY 等同于账号密码，**只能放在服务端环境变量/配置文件**，严禁写进前端 JS、小程序、App 包或公开仓库
- KEY 走 HTTPS header 传输（不进 URL/日志）；库中仅存 sha256 hash
- 疑似泄露：控制台立即「重置」，或联系管理员后台禁用
- 全链路鉴权/限流/队列机制详解与后期安全加固方案（IP 白名单、用量告警、HMAC 签名等）：
  **[docs/API链路与安全方案.md](docs/API链路与安全方案.md)**

---

## 📚 文档索引

| 文档 | 内容 |
|---|---|
| [docs/API链路与安全方案.md](docs/API链路与安全方案.md) | 开放 API 完整链路（关键文件+行号）、泄露面分析、安全加固路线 |
| [docs/API设计.md](docs/API设计.md) | 对外 API 接口设计 |
| [docs/数据库设计.md](docs/数据库设计.md) | 数据表设计 |
| [docs/AI引擎与头像池设计.md](docs/AI引擎与头像池设计.md) | 生图流程与头像池机制 |
| [docs/前端官网设计.md](docs/前端官网设计.md) | 官网页面与 UI 设计 |
| [docs/管理后台设计.md](docs/管理后台设计.md) | 后台功能设计 |
| [deploy/宝塔部署步骤.md](deploy/宝塔部署步骤.md) | 完整部署/排错手册 |
| [deploy/nginx.conf](deploy/nginx.conf) | Nginx 配置参考 |

---

## ⚙️ 配置说明

- 站点配置（站名/域名/积分/豆包 Key/审核/邮箱/短信/支付）全部在**后台六个设置菜单**中维护，存入 `site_settings` 表（优先级高于 `config/*.php`）
- **站点 URL 必填**：后台「站点设置」填公网域名（如 `https://example.com`），队列 Worker 靠它拼接原图地址供豆包下载，不填会导致生成失败
- 修改后端 PHP 代码或豆包 Key 后，**需重启 Supervisor 中的 Worker** 才生效
- 支付未配置微信/支付宝商户参数时，自动回退「人工核销」模式（后台订单手动确认到账）

---

## 📝 备注

- 数据库结构以 `backend/database/schema.sql` 为唯一权威版本；表结构变更直接修改该文件并在文末「变更记录」追加说明
- 生产环境务必：修改 admin 默认密码、`APP_DEBUG=0`、`APP_JWT_SECRET` 使用随机值、配置 HTTPS
