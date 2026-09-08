# 头像引擎 · 部署说明

## 技术栈
- 原生 PHP 8.1+ + 自写路由/MVC（零框架）
- MySQL 5.6、Redis 5.0+、Nginx + PHP-FPM（宝塔面板）
- 后端目录 `backend/`，前端 SSR 由 PHP 模板直出，管理后台为原生 HTML（`admin/`）

## 部署步骤

### 1. 准备运行环境
- 宝塔面板 → 软件商店：安装 Nginx、MySQL 5.6、PHP 8.2（带 redis 扩展）、Redis
- 创建站点（域名 → `backend/public` 为根目录）

### 2. 创建数据库并执行建表
```bash
mysql -uroot -p
> CREATE DATABASE avatar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> CREATE USER 'avatar'@'localhost' IDENTIFIED BY 'change_me';
> GRANT ALL ON avatar.* TO 'avatar'@'localhost';
> exit

mysql -uavatar -pchange_me avatar < backend/database/migrations/001_init.sql
```

### 3. 配置环境变量（Nginx fastcgi_param）
见 `deploy/nginx.conf`，重点修改：
- `DB_DATABASE / DB_USERNAME / DB_PASSWORD`
- `ARK_API_KEY`：豆包 API Key
- `APP_JWT_SECRET`：随机串
- `APP_DEBUG`：生产改 0

### 4. 启动队列 Worker
```bash
# 安装 supervisor 或宝塔「进程管理」守护：
php backend/bin/worker.php avatar:generate --timeout=30 --max=100000
```
Worker 会阻塞消费 `avatar:generate` 队列，调用豆包 → 转存 → 写三池。

### 5. 本地存储（默认 driver=local）
- 上传/生成头像存到 `storage/uploads/`
- Nginx `/uploads/` 静态映射见配置
- 切换对象存储：在 `site_settings` 表 group=storage 加 driver=oss/qiniu/cos，并实现对应上传方法（`ObjectStorageService` 中已预留接口）

### 6. 默认账户
- 用户名 `admin` / 密码 `admin123`（生产环境登录后立即修改 users 表 password）

## 目录速查

```
backend/
├── core/         # 自写框架核心类（Router/Config/DB/Model/Request/Response/View/RedisClient/Log）
├── app/
│   ├── Controllers/Api/   # 对外开发者 API
│   ├── Controllers/Admin/  # 后台接口（Auth 已实现，其余 P1 实现）
│   ├── Controllers/Web/    # 官网 SSR
│   ├── Services/           # 业务服务（Point/Generate/ArkApi/ObjectStorage/Auth）
│   ├── Models/             # 数据模型（基于 DB）
│   ├── Jobs/               # 队列 Worker
│   └── Views/              # PHP 模板
├── config/       # 配置文件
├── database/migrations/   # SQL 建表脚本
├── public/index.php       # 唯一入口
├── bin/worker.php         # 队列 Worker 启动脚本
└── routes/      # 路由定义
admin/           # 管理后台原生 HTML（P1 实现）
frontend/        # 官网静态资源
storage/         # 日志 / 上传 / 缓存
deploy/          # Nginx 配置
```

## P0 已完成

- [x] 数据库 schema（含索引 + 初始数据）
- [x] 自写框架核心：路由 / 配置 / PDO 查询构造器 / 模型基类 / 请求响应 / 视图 / Redis / 日志
- [x] 入口 + 路由（web / api / admin）
- [x] 业务服务：积分（事务+行锁）、生成引擎（队列+重试+退积分）、豆包 API、对象存储抽象
- [x] API：生成 / 查询 / 随机 / 列表 / 上传 / 配置 / KEY 信息 / 用户积分
- [x] 首页 SSR（PHP 模板 + 随机头像展示）
- [x] 后台登录（JWT）+ 其他接口桩

## P1 待办

- [ ] 后台业务接口：用户/订单/积分/KEY/生成记录/头像/设置 完整 CRUD
- [ ] 管理后台 `admin/` 原生 HTML 页面
- [ ] 官网：分类页 / 详情页 / 控制台 / 注册登录 / 充值
- [ ] 对象存储具体实现（OSS/七牛/COS）
- [ ] 缩略图生成（GD/Imagick）
