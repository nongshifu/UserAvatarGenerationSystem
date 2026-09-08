-- 005: generation_records 增加原图地址字段
-- 作用：队列消息丢失（Redis 重启）/ 失败重试时，Worker 可仅凭记录 ID 重建任务
ALTER TABLE `generation_records`
  ADD COLUMN `origin_image_url` varchar(500) NOT NULL DEFAULT '' AFTER `params`;
