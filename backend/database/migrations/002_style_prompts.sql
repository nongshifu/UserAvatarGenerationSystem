-- ============================================================
-- 002 风格完整提示词
-- styles 增加 prompt TEXT 字段：每个风格一套完整预设提示词（替代原来的短后缀拼接）
-- 执行：mysql -uavatar -p avatar < 002_style_prompts.sql
-- ============================================================

ALTER TABLE `styles`
  ADD COLUMN `prompt` TEXT NULL AFTER `prompt_suffix`;

-- 填充内置风格默认预设提示词（与 PresetPromptService 内置一致，用于离线快速初始化）
UPDATE `styles` SET `prompt` = 'Transform this portrait photo into a modern flat cartoon avatar. Keep the subject''s facial features, hairstyle and expression recognizable. Clean bold outlines, smooth cel shading, bright vivid colors, soft gradient background in light blue and lavender. Front-facing head-and-shoulders composition, centered, friendly and energetic. High quality digital illustration, suitable for a profile picture.' WHERE `name` = '卡通' AND (`prompt` IS NULL OR `prompt` = '');

UPDATE `styles` SET `prompt` = 'Turn this portrait photo into a super-deformed (SD/chibi) Q-version avatar: oversized head with a big cute face, large sparkling eyes, tiny body proportions roughly 2-heads tall. Keep hairstyle and key facial traits recognizable. Soft rounded shapes, glossy cel shading, cheerful pastel colors, simple solid-color background. Kawaii sticker style, front-facing, centered head-and-shoulders bust, adorable and playful.' WHERE `name` = 'Q版' AND (`prompt` IS NULL OR `prompt` = '');

UPDATE `styles` SET `prompt` = 'Reimagine this portrait as a cyberpunk avatar: futuristic neon-lit style, glowing magenta and cyan rim lighting, high-tech jacket with subtle circuit details, holographic accents, reflective wet-city background bokeh. Keep the face recognizable with sharp confident expression. Cinematic lighting, ultra detailed digital art, dark blue and purple palette, front-facing bust portrait, cool and edgy mood.' WHERE `name` = '赛博朋克' AND (`prompt` IS NULL OR `prompt` = '');

UPDATE `styles` SET `prompt` = 'Paint this portrait as a classic oil painting avatar in the style of fine art portraiture: rich textured brush strokes, warm Rembrandt lighting, deep earthy tones with golden highlights, subtle canvas texture. Keep facial features and expression faithful. Dark muted studio background, elegant and timeless. Museum-quality oil portrait, front-facing head-and-shoulders composition, sophisticated atmosphere.' WHERE `name` = '油画' AND (`prompt` IS NULL OR `prompt` = '');

UPDATE `styles` SET `prompt` = 'Render this portrait as a delicate hand-painted watercolor avatar: soft bleeding pigments, gentle translucent washes, light sketchy ink outlines, airy white space, pastel palette with subtle color splatters. Keep the face gentle and recognizable. Warm cream paper background, artistic and fresh. Traditional watercolor illustration, front-facing bust, soft dreamy mood.' WHERE `name` = '水彩' AND (`prompt` IS NULL OR `prompt` = '');

UPDATE `styles` SET `prompt` = 'Convert this portrait into a retro 16-bit pixel art avatar: crisp pixel grid, limited vibrant color palette, clear dithering shading, blocky expressive features while keeping hairstyle and face recognizable. Clean transparent-friendly solid background, centered head-and-shoulders sprite, classic RPG character portrait style, nostalgic 90s game aesthetic.' WHERE `name` = '像素风' AND (`prompt` IS NULL OR `prompt` = '');

-- 自定义风格（未匹配内置名称的）兜底为通用卡通提示词
UPDATE `styles` SET `prompt` = 'Generate a cartoon avatar based on this photo, keep facial features, smooth lines, vivid colors, front-facing head-and-shoulders portrait, clean simple background, high quality illustration suitable for a profile picture.'
WHERE (`prompt` IS NULL OR `prompt` = '');
