<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * 预设提示词库
 *
 * 内置各风格默认提示词（与代码一起发布、版本可控）：
 *  - 后台「重置默认」时写回 DB
 *  - 新增风格未填提示词时兜底
 * 生成时以 DB 中 styles.prompt 为准，本服务只在重置/兜底时使用
 */
final class PresetPromptService
{
    /**
     * 内置风格默认提示词（按风格名称匹配）
     * @return array<string,string>
     */
    public static function styleDefaults(): array
    {
        return [
            '卡通' => 'Transform this portrait photo into a modern flat cartoon avatar. Keep the subject\'s facial features, hairstyle and expression recognizable. Clean bold outlines, smooth cel shading, bright vivid colors, soft gradient background in light blue and lavender. Front-facing head-and-shoulders composition, centered, friendly and energetic. High quality digital illustration, suitable for a profile picture.',
            'Q版' => 'Turn this portrait photo into a super-deformed (SD/chibi) Q-version avatar: oversized head with a big cute face, large sparkling eyes, tiny body proportions roughly 2-heads tall. Keep hairstyle and key facial traits recognizable. Soft rounded shapes, glossy cel shading, cheerful pastel colors, simple solid-color background. Kawaii sticker style, front-facing, centered head-and-shoulders bust, adorable and playful.',
            '赛博朋克' => 'Reimagine this portrait as a cyberpunk avatar: futuristic neon-lit style, glowing magenta and cyan rim lighting, high-tech jacket with subtle circuit details, holographic accents, reflective wet-city background bokeh. Keep the face recognizable with sharp confident expression. Cinematic lighting, ultra detailed digital art, dark blue and purple palette, front-facing bust portrait, cool and edgy mood.',
            '油画' => 'Paint this portrait as a classic oil painting avatar in the style of fine art portraiture: rich textured brush strokes, warm Rembrandt lighting, deep earthy tones with golden highlights, subtle canvas texture. Keep facial features and expression faithful. Dark muted studio background, elegant and timeless. Museum-quality oil portrait, front-facing head-and-shoulders composition, sophisticated atmosphere.',
            '水彩' => 'Render this portrait as a delicate hand-painted watercolor avatar: soft bleeding pigments, gentle translucent washes, light sketchy ink outlines, airy white space, pastel palette with subtle color splatters. Keep the face gentle and recognizable. Warm cream paper background, artistic and fresh. Traditional watercolor illustration, front-facing bust, soft dreamy mood.',
            '像素风' => 'Convert this portrait into a retro 16-bit pixel art avatar: crisp pixel grid, limited vibrant color palette, clear dithering shading, blocky expressive features while keeping hairstyle and face recognizable. Clean transparent-friendly solid background, centered head-and-shoulders sprite, classic RPG character portrait style, nostalgic 90s game aesthetic.',
        ];
    }

    /**
     * 自定义/未匹配风格的兜底提示词
     */
    public static function genericStylePrompt(): string
    {
        return 'Generate a cartoon avatar based on this photo, keep facial features, smooth lines, vivid colors, front-facing head-and-shoulders portrait, clean simple background, high quality illustration suitable for a profile picture.';
    }

    /**
     * 基础模板默认提示词（未选风格时使用）
     */
    public static function baseTemplateDefault(): string
    {
        return 'Transform this portrait photo into a stylized cartoon avatar. Keep the subject\'s facial features, hairstyle and expression recognizable. Clean outlines, smooth cel shading, vivid colors, front-facing head-and-shoulders composition, centered, simple soft background, high quality digital illustration suitable for a profile picture.';
    }

    /**
     * 取某个风格的默认提示词（按名称匹配，未匹配返回兜底）
     */
    public static function defaultForStyleName(string $styleName): string
    {
        return self::styleDefaults()[$styleName] ?? self::genericStylePrompt();
    }

    /**
     * 重置单个风格为默认提示词，返回写入的内容
     */
    public static function resetStyle(int $styleId): string
    {
        $row = DB::table('styles')->where('id', $styleId)->first();
        if (!$row) {
            throw new \RuntimeException('风格不存在', 4004);
        }
        $prompt = self::defaultForStyleName((string)$row['name']);
        DB::table('styles')->where('id', $styleId)->update(['prompt' => $prompt]);
        return $prompt;
    }

    /**
     * 全部风格重置为默认提示词，返回重置数量
     */
    public static function resetAllStyles(): int
    {
        $styles = DB::table('styles')->all();
        $count = 0;
        foreach ($styles as $s) {
            $prompt = self::defaultForStyleName((string)$s['name']);
            DB::table('styles')->where('id', (int)$s['id'])->update(['prompt' => $prompt]);
            $count++;
        }
        return $count;
    }
}
