-- ============================================================
-- 004: 上传图片 AI 预审配置
-- 开启后，用户上传图片在图生图之前先调用豆包图像理解模型（/responses）
-- 判断图片是否合规、是否适合作为头像；未通过则拒绝生成（不扣积分）
-- ============================================================

INSERT IGNORE INTO `site_settings` (`group_key`,`item_key`,`item_value`,`item_type`,`description`) VALUES
('audit','precheck_enabled','false','boolean','上传前 AI 图片预审开关'),
('audit','precheck_model','doubao-seed-2-0-lite-260428','string','预审图像理解模型'),
('audit','precheck_prompt','','string','预审审核提示词（留空用默认规则）'),
('audit','precheck_fail_open','true','boolean','审核服务异常时放行');
