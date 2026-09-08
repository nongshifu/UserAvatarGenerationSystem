-- ============================================================
-- 头像引擎系统 · 完整数据库结构（唯一权威版本）
-- 目标数据库：MySQL 5.6，字符集 utf8mb4
-- 全新部署执行：mysql -u<user> -p avatar < schema.sql
--
-- 说明：本文件已合并历史迁移 001~005 的全部变更：
--   002 styles.prompt 完整提示词字段（已含在建表与初始数据中）
--   003 users.phone_verified 手机验证字段（已含在建表中）
--   004 audit.precheck_* 上传预审配置（已含在 site_settings 初始数据中）
--   005 generation_records.origin_image_url 原图地址字段（已含在建表中）
-- 后续表结构变更：直接修改本文件，并在文件末尾「变更记录」处追加说明。
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 用户与权限 ────────────────────────────────────────

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `phone_verified` tinyint(1) NOT NULL DEFAULT 0,
  `password` varchar(255) NOT NULL,
  `nickname` varchar(50) NOT NULL DEFAULT '',
  `avatar` varchar(500) NOT NULL DEFAULT '',
  `role` enum('admin','developer','user') NOT NULL DEFAULT 'user',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `points` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_recharge` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_consume` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`),
  UNIQUE KEY `uniq_email` (`email`),
  UNIQUE KEY `uniq_phone` (`phone`),
  KEY `idx_role_status` (`role`,`status`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_keys`;
CREATE TABLE `user_keys` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `api_key_hash` char(64) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `callback_url` varchar(500) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `rate_limit` int(11) NOT NULL DEFAULT 0,
  `daily_limit` int(11) NOT NULL DEFAULT 0,
  `used_today` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_api_key` (`api_key`),
  UNIQUE KEY `uniq_api_key_hash` (`api_key_hash`),
  KEY `idx_user_status` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sub_users`;
CREATE TABLE `sub_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_id` bigint(20) UNSIGNED NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `nickname` varchar(50) NOT NULL DEFAULT '',
  `avatar` varchar(500) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_key_identifier` (`key_id`,`identifier`),
  KEY `idx_key_id` (`key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 积分与订单 ────────────────────────────────────────

DROP TABLE IF EXISTS `point_products`;
CREATE TABLE `point_products` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `points` int(10) UNSIGNED NOT NULL,
  `bonus_points` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `description` varchar(500) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sort_status` (`sort`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `points` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','paid','refunded','closed') NOT NULL DEFAULT 'pending',
  `pay_method` varchar(20) NOT NULL DEFAULT '',
  `pay_trade_no` varchar(100) NOT NULL DEFAULT '',
  `paid_at` timestamp NULL DEFAULT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `admin_remark` varchar(500) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_no` (`order_no`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `point_logs`;
CREATE TABLE `point_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('register','recharge','consume','refund','admin_add','admin_sub','audit_refund') NOT NULL,
  `change` int(11) NOT NULL,
  `balance` int(10) UNSIGNED NOT NULL,
  `related_id` bigint(20) UNSIGNED DEFAULT NULL,
  `remark` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 头像与生成 ────────────────────────────────────────

DROP TABLE IF EXISTS `avatars`;
CREATE TABLE `avatars` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `key_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sub_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `origin_url` varchar(500) NOT NULL,
  `result_url` varchar(500) NOT NULL DEFAULT '',
  `result_thumb_url` varchar(500) NOT NULL DEFAULT '',
  `prompt_id` bigint(20) UNSIGNED DEFAULT NULL,
  `style_id` bigint(20) UNSIGNED DEFAULT NULL,
  `color_id` bigint(20) UNSIGNED DEFAULT NULL,
  `shape_id` bigint(20) UNSIGNED DEFAULT NULL,
  `prompt_text` text,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `audit_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `status` enum('generating','success','failed') NOT NULL DEFAULT 'generating',
  `width` int(11) NOT NULL DEFAULT 0,
  `height` int(11) NOT NULL DEFAULT 0,
  `file_size` int(11) NOT NULL DEFAULT 0,
  `views` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_public_audit_status` (`is_public`,`audit_status`,`status`),
  KEY `idx_key_status` (`key_id`,`status`),
  KEY `idx_subuser_created` (`sub_user_id`,`created_at`),
  KEY `idx_style_color_shape` (`style_id`,`color_id`,`shape_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `generation_records`;
CREATE TABLE `generation_records` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `key_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sub_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `avatar_id` bigint(20) UNSIGNED DEFAULT NULL,
  `prompt_id` bigint(20) UNSIGNED DEFAULT NULL,
  `style_id` bigint(20) UNSIGNED DEFAULT NULL,
  `color_id` bigint(20) UNSIGNED DEFAULT NULL,
  `shape_id` bigint(20) UNSIGNED DEFAULT NULL,
  `params` text,
  `origin_image_url` varchar(500) NOT NULL DEFAULT '',
  `cost_points` int(11) NOT NULL DEFAULT 0,
  `api_model` varchar(50) NOT NULL DEFAULT '',
  `api_request_id` varchar(100) NOT NULL DEFAULT '',
  `api_response` text,
  `status` enum('pending','processing','success','failed') NOT NULL DEFAULT 'pending',
  `error_msg` varchar(500) DEFAULT NULL,
  `retry_count` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`,`created_at`),
  KEY `idx_key_created` (`key_id`,`created_at`),
  KEY `idx_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `prompt_templates`;
CREATE TABLE `prompt_templates` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `prompt` text,
  `category` varchar(50) NOT NULL DEFAULT '',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category_status` (`category`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `styles`;
CREATE TABLE `styles` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `prompt_suffix` varchar(255) NOT NULL DEFAULT '',
  `prompt` text NULL,
  `icon` varchar(500) NOT NULL DEFAULT '',
  `cost_points` int(11) NOT NULL DEFAULT 0,
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `colors`;
CREATE TABLE `colors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `hex` char(7) NOT NULL DEFAULT '#000000',
  `prompt_suffix` varchar(255) NOT NULL DEFAULT '',
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shapes`;
CREATE TABLE `shapes` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `prompt_suffix` varchar(255) NOT NULL DEFAULT '',
  `icon` varchar(500) NOT NULL DEFAULT '',
  `sort` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 系统设置 ──────────────────────────────────────────

DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE `site_settings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_key` varchar(50) NOT NULL,
  `item_key` varchar(100) NOT NULL,
  `item_value` text,
  `item_type` varchar(20) NOT NULL DEFAULT 'string',
  `description` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_group_item` (`group_key`,`item_key`),
  KEY `idx_group` (`group_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `seo_settings`;
CREATE TABLE `seo_settings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `page` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL DEFAULT '',
  `keywords` varchar(500) NOT NULL DEFAULT '',
  `description` varchar(500) NOT NULL DEFAULT '',
  `og_image` varchar(500) NOT NULL DEFAULT '',
  `canonical` varchar(500) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_page` (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_type` varchar(50) NOT NULL DEFAULT '',
  `target_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(500) NOT NULL DEFAULT '',
  `detail` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_created` (`admin_id`,`created_at`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ── 初始数据 ──────────────────────────────────────────
-- 超级管理员：用户名 admin，密码 admin123（bcrypt hash，建议首次登录立即修改）
INSERT INTO `users` (`username`,`email`,`password`,`role`,`status`,`points`)
VALUES ('admin','admin@localhost','$2y$10$t5mI8VjUvbv065XhDGwkeOEUSJrL/dvcvV2iIgMdwTox7gZ8rSH.q','admin',1,0);

INSERT INTO `styles` (`name`,`prompt_suffix`,`prompt`,`cost_points`,`sort`,`status`) VALUES
('卡通','cartoon style','Transform this portrait photo into a modern flat cartoon avatar. Keep the subject''s facial features, hairstyle and expression recognizable. Clean bold outlines, smooth cel shading, bright vivid colors, soft gradient background in light blue and lavender. Front-facing head-and-shoulders composition, centered, friendly and energetic. High quality digital illustration, suitable for a profile picture.',0,1,1),
('Q版','chibi style','Turn this portrait photo into a super-deformed (SD/chibi) Q-version avatar: oversized head with a big cute face, large sparkling eyes, tiny body proportions roughly 2-heads tall. Keep hairstyle and key facial traits recognizable. Soft rounded shapes, glossy cel shading, cheerful pastel colors, simple solid-color background. Kawaii sticker style, front-facing, centered head-and-shoulders bust, adorable and playful.',0,2,1),
('赛博朋克','cyberpunk style','Reimagine this portrait as a cyberpunk avatar: futuristic neon-lit style, glowing magenta and cyan rim lighting, high-tech jacket with subtle circuit details, holographic accents, reflective wet-city background bokeh. Keep the face recognizable with sharp confident expression. Cinematic lighting, ultra detailed digital art, dark blue and purple palette, front-facing bust portrait, cool and edgy mood.',5,3,1),
('油画','oil painting style','Paint this portrait as a classic oil painting avatar in the style of fine art portraiture: rich textured brush strokes, warm Rembrandt lighting, deep earthy tones with golden highlights, subtle canvas texture. Keep facial features and expression faithful. Dark muted studio background, elegant and timeless. Museum-quality oil portrait, front-facing head-and-shoulders composition, sophisticated atmosphere.',8,4,1),
('水彩','watercolor style','Render this portrait as a delicate hand-painted watercolor avatar: soft bleeding pigments, gentle translucent washes, light sketchy ink outlines, airy white space, pastel palette with subtle color splatters. Keep the face gentle and recognizable. Warm cream paper background, artistic and fresh. Traditional watercolor illustration, front-facing bust, soft dreamy mood.',5,5,1),
('像素风','pixel art style','Convert this portrait into a retro 16-bit pixel art avatar: crisp pixel grid, limited vibrant color palette, clear dithering shading, blocky expressive features while keeping hairstyle and face recognizable. Clean transparent-friendly solid background, centered head-and-shoulders sprite, classic RPG character portrait style, nostalgic 90s game aesthetic.',3,6,1);

INSERT INTO `colors` (`name`,`hex`,`prompt_suffix`,`sort`,`status`) VALUES
('暖色','#FF8866','warm color palette with orange and coral tones',1,1),
('冷色','#6699FF','cool color palette with blue and teal tones',2,1),
('黑白','#333333','monochrome black and white tones with high contrast',3,1),
('马卡龙','#FFB3BA','soft pastel macaron color palette, mint pink and cream',4,1);

INSERT INTO `shapes` (`name`,`prompt_suffix`,`sort`,`status`) VALUES
('圆形','composed inside a circular frame border',1,1),
('方形','composed inside a rounded square frame border',2,1),
('无边框','full bleed without any frame border, edge to edge',3,1);

INSERT INTO `prompt_templates` (`title`,`prompt`,`category`,`is_default`,`status`,`sort`) VALUES
('通用卡通化（无风格时使用）','Transform this portrait photo into a stylized cartoon avatar. Keep the subject''s facial features, hairstyle and expression recognizable. Clean outlines, smooth cel shading, vivid colors, front-facing head-and-shoulders composition, centered, simple soft background, high quality digital illustration suitable for a profile picture.','general',1,1,1);

INSERT INTO `point_products` (`name`,`price`,`points`,`bonus_points`,`sort`,`status`,`description`) VALUES
('10元/100积分',10.00,100,0,1,1,'基础套餐'),
('30元/350积分',30.00,300,50,2,1,'送50积分'),
('50元/600积分',50.00,500,100,3,1,'送100积分'),
('100元/1300积分',100.00,1000,300,4,1,'送300积分');

-- 站点配置：后台「站点设置 / 积分配置 / API生图 / 审核设置 / 邮箱配置 / 短信配置」六个分组
-- 注意：DB 值优先级高于 backend/config/*.php 文件；bool 值统一存 '1'/'0' 字符串
INSERT INTO `site_settings` (`group_key`,`item_key`,`item_value`,`item_type`,`description`) VALUES
-- 站点设置（basic）
('basic','site_name','头像引擎','string','站名'),
('basic','site_url','','string','站点 URL（必填！公网完整域名、结尾不带 /，Worker 拼原图地址用）'),
('basic','site_logo','','string','LOGO URL'),
('basic','icp','','string','备案号'),
('basic','customer_service','','string','客服'),
('basic','footer_text','','string','底部版权文案，支持 {year} {site} 占位符'),
('basic','analytics_code','','string','统计代码'),
-- 积分配置（points）
('points','register_bonus','100','number','注册赠送'),
('points','default_cost','10','number','默认消耗'),
('points','min_balance','1','number','最低阈值'),
-- API 生图（api）
('api','ark_api_key','','string','豆包 API Key'),
('api','ark_model','doubao-seedream-5-0-260128','string','模型名'),
('api','image_size','2k','string','图片尺寸（小写 1k/2k/3k/4k）'),
('api','watermark','true','boolean','水印'),
-- 审核设置（audit）
('audit','enabled','false','boolean','审核总开关（生成结果内容审核）'),
('audit','auto_approve','true','boolean','通过自动入池'),
('audit','refund_on_reject','true','boolean','驳回退积分'),
('audit','security_api_key','','string','内容审核 API Key'),
('audit','security_endpoint','','string','内容审核接口地址'),
('audit','precheck_enabled','false','boolean','上传前 AI 图片预审开关'),
('audit','precheck_model','doubao-seed-2-0-lite-260428','string','预审图像理解模型'),
('audit','precheck_prompt','','string','预审审核提示词（留空用默认规则）'),
('audit','precheck_fail_open','true','boolean','审核服务异常时放行'),
-- 邮箱配置（mail，SMTP 发信找回密码）
('mail','mail_enabled','false','boolean','启用邮箱发信'),
('mail','smtp_host','','string','SMTP 服务器（如 smtp.qq.com）'),
('mail','smtp_port','465','number','SMTP 端口（SSL 推荐 465）'),
('mail','smtp_user','','string','发件邮箱'),
('mail','smtp_pass','','string','SMTP 授权码（非邮箱登录密码）'),
('mail','from_name','','string','发件人显示名（留空用站点名称）'),
-- 短信配置（sms，接口盒子 apihz.cn）
('sms','sms_enabled','false','boolean','启用短信验证码'),
('sms','sms_force_verify','false','boolean','强制手机验证才能生成'),
('sms','apihz_id','','string','接口盒子开发者 ID'),
('sms','apihz_key','','string','接口盒子通讯秘钥'),
('sms','apihz_url','','string','短信代发接口地址（留空用默认）'),
('sms','sms_dynamic','false','boolean','启用动态秘钥验证'),
('sms','sms_dmsg','','string','动态秘钥预留信息（dmsg）'),
-- 支付配置（payment，预留；当前走人工确认到账）
('payment','wechat_appid','','string','微信 AppID'),
('payment','wechat_mchid','','string','微信商户号'),
('payment','wechat_api_key','','string','微信 API 密钥'),
('payment','wechat_notify_url','','string','微信回调地址'),
('payment','alipay_appid','','string','支付宝 AppID'),
('payment','alipay_private_key','','string','支付宝应用私钥'),
('payment','alipay_public_key','','string','支付宝公钥'),
('payment','alipay_notify_url','','string','支付宝回调地址');

INSERT INTO `seo_settings` (`page`,`title`,`keywords`,`description`) VALUES
('home','AI 头像生成引擎 | 一键生成卡通头像','头像,卡通头像,AI头像,头像生成','上传真人头像，AI 生成个性卡通头像，支持多种风格、颜色、形状。'),
('category','头像分类 - {style} | 头像引擎','头像,{style},分类','浏览 {style} 风格头像集合。'),
('avatar','头像详情 #{id} | 头像引擎','头像,卡通头像','头像详情页。');

-- ============================================================
-- 变更记录
-- 2026-09 合并历史迁移 001~005 为单一 schema.sql；补齐 mail/sms 配置初始项；
--          user_keys.used_today 修正为 UNSIGNED；image_size 初始值改为小写 2k
-- ============================================================
