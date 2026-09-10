<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Models\PromptTemplate;
use App\Services\PresetPromptService;
use App\Services\SiteSettingService;
use App\Services\UserService;

/**
 * 提示词管理
 * - 基础模板（未选风格时使用）
 * - 各风格完整预设提示词（生成时的核心提示词，可编辑/重置默认）
 * - 颜色/形状后缀（附加在核心提示词之后）
 */
final class PromptController
{
    /**
     * GET /admin-api/prompts
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $defaults = PresetPromptService::styleDefaults();

        $styles = DB::table('styles')->orderBy('sort', 'ASC')->all();
        $styleOut = [];
        foreach ($styles as $s) {
            $default = PresetPromptService::defaultForStyleName((string)$s['name']);
            $current = trim((string)($s['prompt'] ?? ''));
            $styleOut[] = [
                'id'           => (int)$s['id'],
                'name'         => (string)$s['name'],
                'cost_points'  => (int)$s['cost_points'],
                'sort'         => (int)$s['sort'],
                'status'       => (int)$s['status'],
                'prompt'       => $current !== '' ? $current : $default,
                'default_prompt' => $default,
                'is_builtin'   => isset($defaults[(string)$s['name']]),
                'customized'   => $current !== '' && $current !== $default,
            ];
        }

        $template = PromptTemplate::getDefault();
        $templateOut = [
            'id'      => $template ? (int)$template->getKey() : 0,
            'title'   => $template ? (string)$template->title : '通用卡通化',
            'prompt'  => $template ? (string)$template->prompt : PresetPromptService::baseTemplateDefault(),
            'default_prompt' => PresetPromptService::baseTemplateDefault(),
        ];
        $templateOut['customized'] = trim($templateOut['prompt']) !== ''
            && trim($templateOut['prompt']) !== trim($templateOut['default_prompt']);

        $colors = DB::table('colors')->orderBy('sort', 'ASC')->all();
        $shapes = DB::table('shapes')->orderBy('sort', 'ASC')->all();
        $map = static fn (array $rows) => array_map(static fn ($r) => [
            'id'            => (int)$r['id'],
            'name'          => (string)$r['name'],
            'prompt_suffix' => (string)($r['prompt_suffix'] ?? ''),
        ], $rows);

        $res->json([
            'template' => $templateOut,
            'styles'   => $styleOut,
            'colors'   => $map($colors),
            'shapes'   => $map($shapes),
            'points'   => SiteSettingService::getGroup('points'),
        ]);
    }

    /**
     * POST /admin-api/prompts/styles  body: {name, prompt, cost_points?, sort?, status?}
     * 新增一个风格类型（前台生成页立即可见）
     */
    public function storeStyle(Request $req, Response $res, array $params): void
    {
        $name = trim((string)$req->input('name', ''));
        $prompt = trim((string)$req->input('prompt', ''));
        if ($name === '' || mb_strlen($name) > 50) {
            $res->error(4001, '风格名称不能为空，且不超过 50 个字');
            return;
        }
        if ($prompt === '') {
            $res->error(4001, '提示词不能为空');
            return;
        }
        if (DB::table('styles')->where('name', $name)->first()) {
            $res->error(4009, '已存在同名风格：' . $name);
            return;
        }
        $cost = $req->input('cost_points') !== null ? (int)$req->input('cost_points') : 0;
        if ($cost < 0) {
            $res->error(4001, '积分消耗不能为负数');
            return;
        }
        // 排序权重留空时自动排到末尾
        $sort = $req->input('sort');
        if ($sort === null || $sort === '') {
            $maxSort = (int)DB::table('styles')->orderBy('sort', 'DESC')->value('sort');
            $sort = $maxSort + 1;
        } else {
            $sort = (int)$sort;
        }
        $status = (int)$req->input('status', 1) === 0 ? 0 : 1;

        $id = DB::table('styles')->insert([
            'name'        => $name,
            'prompt'      => $prompt,
            'cost_points' => $cost,
            'sort'        => $sort,
            'status'      => $status,
        ]);
        UserService::flushDimensionCache();
        $res->json(['created' => true, 'id' => $id]);
    }

    /**
     * PUT /admin-api/prompts/styles/{id}
     * body: {name?, prompt?, cost_points?, sort?, status?}
     */
    public function updateStyle(Request $req, Response $res, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $res->error(4001, '风格 ID 错误');
            return;
        }
        $exists = DB::table('styles')->where('id', $id)->first();
        if (!$exists) {
            $res->error(4004, '风格不存在', 404);
            return;
        }
        $update = [];

        // 名称（改名时校验唯一）
        if ($req->input('name') !== null) {
            $name = trim((string)$req->input('name', ''));
            if ($name === '' || mb_strlen($name) > 50) {
                $res->error(4001, '风格名称不能为空，且不超过 50 个字');
                return;
            }
            $dup = DB::table('styles')->where('name', $name)->first();
            if ($dup && (int)$dup['id'] !== $id) {
                $res->error(4009, '已存在同名风格：' . $name);
                return;
            }
            $update['name'] = $name;
        }

        // 提示词
        if ($req->input('prompt') !== null) {
            $prompt = trim((string)$req->input('prompt', ''));
            if ($prompt === '') {
                $res->error(4001, '提示词不能为空');
                return;
            }
            $update['prompt'] = $prompt;
        }

        // 积分消耗：非负整数
        if ($req->input('cost_points') !== null) {
            $cost = (int)$req->input('cost_points');
            if ($cost < 0) {
                $res->error(4001, '积分消耗不能为负数');
                return;
            }
            $update['cost_points'] = $cost;
        }

        // 排序权重
        if ($req->input('sort') !== null && $req->input('sort') !== '') {
            $update['sort'] = (int)$req->input('sort');
        }

        // 状态：0 停用 / 1 启用
        if ($req->input('status') !== null) {
            $update['status'] = (int)$req->input('status') === 0 ? 0 : 1;
        }

        if ($update === []) {
            $res->error(4001, '没有需要更新的内容');
            return;
        }
        DB::table('styles')->where('id', $id)->update($update);
        UserService::flushDimensionCache();
        $res->json(['updated' => true, 'id' => $id]);
    }

    /**
     * DELETE /admin-api/prompts/styles/{id}
     * 删除风格；历史生成记录保留 style_id（风格缺失时自动回退基础模板，不影响历史）
     */
    public function destroyStyle(Request $req, Response $res, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        $exists = DB::table('styles')->where('id', $id)->first();
        if (!$exists) {
            $res->error(4004, '风格不存在', 404);
            return;
        }
        DB::table('styles')->where('id', $id)->delete();
        UserService::flushDimensionCache();
        $res->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * POST /admin-api/prompts/styles/{id}/reset  重置单个风格为默认
     */
    public function resetStyle(Request $req, Response $res, array $params): void
    {
        $id = (int)($params['id'] ?? 0);
        try {
            $prompt = PresetPromptService::resetStyle($id);
        } catch (\RuntimeException $e) {
            $res->error((int)$e->getCode() ?: 4004, $e->getMessage(), 404);
            return;
        }
        UserService::flushDimensionCache();
        $res->json(['reset' => true, 'id' => $id, 'prompt' => $prompt]);
    }

    /**
     * POST /admin-api/prompts/reset-all  全部风格重置默认
     */
    public function resetAll(Request $req, Response $res, array $params): void
    {
        $count = PresetPromptService::resetAllStyles();
        UserService::flushDimensionCache();
        $res->json(['reset' => true, 'count' => $count]);
    }

    /**
     * PUT /admin-api/prompts/template  body: {prompt}
     * 更新基础模板（默认模板）提示词
     */
    public function updateTemplate(Request $req, Response $res, array $params): void
    {
        $prompt = trim((string)$req->input('prompt', ''));
        if ($prompt === '') {
            $res->error(4001, '提示词不能为空');
            return;
        }
        $template = PromptTemplate::getDefault();
        if ($template) {
            DB::table('prompt_templates')->where('id', (int)$template->getKey())->update(['prompt' => $prompt]);
        } else {
            DB::table('prompt_templates')->insert([
                'title'      => '通用卡通化（无风格时使用）',
                'prompt'     => $prompt,
                'category'   => 'general',
                'is_default' => 1,
                'status'     => 1,
                'sort'       => 1,
            ]);
        }
        $res->json(['updated' => true]);
    }

    /**
     * POST /admin-api/prompts/template/reset  基础模板重置默认
     */
    public function resetTemplate(Request $req, Response $res, array $params): void
    {
        $prompt = PresetPromptService::baseTemplateDefault();
        $template = PromptTemplate::getDefault();
        if ($template) {
            DB::table('prompt_templates')->where('id', (int)$template->getKey())->update(['prompt' => $prompt]);
        } else {
            DB::table('prompt_templates')->insert([
                'title'      => '通用卡通化（无风格时使用）',
                'prompt'     => $prompt,
                'category'   => 'general',
                'is_default' => 1,
                'status'     => 1,
                'sort'       => 1,
            ]);
        }
        $res->json(['reset' => true, 'prompt' => $prompt]);
    }

    /**
     * PUT /admin-api/prompts/suffix  body: {type: color|shape, id, prompt_suffix}
     */
    public function updateSuffix(Request $req, Response $res, array $params): void
    {
        $type = (string)$req->input('type', '');
        $id = (int)$req->input('id', 0);
        $suffix = trim((string)$req->input('prompt_suffix', ''));
        if (!in_array($type, ['color', 'shape'], true) || $id <= 0) {
            $res->error(4001, '参数错误（type 须为 color/shape）');
            return;
        }
        $table = $type === 'color' ? 'colors' : 'shapes';
        $exists = DB::table($table)->where('id', $id)->first();
        if (!$exists) {
            $res->error(4004, '记录不存在', 404);
            return;
        }
        DB::table($table)->where('id', $id)->update(['prompt_suffix' => $suffix]);
        UserService::flushDimensionCache();
        $res->json(['updated' => true]);
    }
}
