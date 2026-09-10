<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\RedisClient;

/**
 * 用户端业务 Service
 * - 余额（带 Redis 缓存）
 * - 我的头像历史（子用户/个人池）
 * - 积分流水
 * - 生成记录
 */
final class UserService
{
    /**
     * 余额（缓存 30s）
     */
    public static function balance(int $userId): int
    {
        $key = 'user:balance:' . $userId;
        $cached = RedisClient::get($key);
        if ($cached !== null) {
            return (int)$cached;
        }
        $row = DB::table('users')->select('points')->where('id', $userId)->first();
        $bal = $row ? (int)$row['points'] : 0;
        RedisClient::set($key, (string)$bal, 30);
        return $bal;
    }

    /**
     * 个人头像历史（user_id = $userId，按时间倒序）
     */
    public static function myAvatars(int $userId, int $page = 1, int $perPage = 24): array
    {
        return DB::table('avatars')
            ->where('user_id', $userId)
            ->where('status', 'success')
            ->orderBy('id', 'DESC')
            ->paginate($page, $perPage);
    }

    /**
     * 积分流水（支持关键词搜备注、类型筛选）
     *
     * @param array{keyword?:string,type?:string} $filters
     */
    public static function pointLogs(int $userId, int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $q = DB::table('point_logs')->where('user_id', $userId);

        $type = (string)($filters['type'] ?? '');
        $allowTypes = ['register','recharge','consume','refund','admin_add','admin_sub','audit_refund'];
        if ($type !== '' && in_array($type, $allowTypes, true)) {
            $q->where('type', $type);
        }
        $kw = trim((string)($filters['keyword'] ?? ''));
        if ($kw !== '') {
            $q->where('remark', '%' . $kw . '%', 'LIKE');
        }

        return $q->orderBy('id', 'DESC')->paginate($page, $perPage);
    }

    /**
     * 每日积分序列（控制台积分记录图表用）
     * 返回连续日期序列：date / income（当日收入）/ expense（当日支出）/ net（净变动）/ balance（期末余额）
     * @return array<int,array{date:string,income:int,expense:int,net:int,balance:int}>
     */
    public static function pointDailySeries(int $userId, int $days = 365): array
    {
        $start = date('Y-m-d 00:00:00', strtotime('-' . (max(1, $days) - 1) . ' days'));

        // 窗口起点之前的最后余额作为序列起始余额
        $prev = DB::table('point_logs')
            ->select('balance')
            ->where('user_id', $userId)
            ->where('created_at', $start, '<')
            ->orderBy('id', 'DESC')
            ->first();
        $opening = $prev ? (int)$prev['balance'] : null;

        // 不指定列：change 是 MySQL 保留字，DB 查询器编译列名不加反引号，指定列会触发语法错误
        $rows = DB::table('point_logs')
            ->where('user_id', $userId)
            ->where('created_at', $start, '>=')
            ->orderBy('id', 'ASC')
            ->all();

        // 按天聚合（id 升序，最后一条即期末余额）
        $byDay = [];
        foreach ($rows as $r) {
            $d = substr((string)$r['created_at'], 0, 10);
            if (!isset($byDay[$d])) {
                $byDay[$d] = ['income' => 0, 'expense' => 0, 'net' => 0, 'balance' => (int)$r['balance']];
            }
            $ch = (int)$r['change'];
            if ($ch > 0) {
                $byDay[$d]['income'] += $ch;
            } else {
                $byDay[$d]['expense'] += -$ch;
            }
            $byDay[$d]['net'] += $ch;
            $byDay[$d]['balance'] = (int)$r['balance'];
        }

        // 从首个有数据的日期（不早于窗口起点）到今天，生成连续日期序列，无流水的日期沿用前一日余额
        $today = date('Y-m-d');
        $from = $rows ? substr((string)$rows[0]['created_at'], 0, 10) : $today;
        $windowStart = date('Y-m-d', strtotime($start));
        if ($from < $windowStart) {
            $from = $windowStart;
        }
        $series = [];
        $bal = $opening;
        for ($t = strtotime($from); $t <= strtotime($today); $t = strtotime('+1 day', $t)) {
            $d = date('Y-m-d', $t);
            if (isset($byDay[$d])) {
                $bal = $byDay[$d]['balance'];
                $series[] = ['date' => $d, 'income' => $byDay[$d]['income'], 'expense' => $byDay[$d]['expense'], 'net' => $byDay[$d]['net'], 'balance' => $bal];
            } else {
                $series[] = ['date' => $d, 'income' => 0, 'expense' => 0, 'net' => 0, 'balance' => $bal ?? 0];
            }
        }
        return $series;
    }

    /**
     * 每日生成序列（控制台生成记录图表用）
     * 返回连续日期序列：date / total（当日生成数）/ success / failed / points（当日消耗积分合计）
     * @return array<int,array{date:string,total:int,success:int,failed:int,points:int}>
     */
    public static function generationDailySeries(int $userId, int $days = 365): array
    {
        $start = date('Y-m-d 00:00:00', strtotime('-' . (max(1, $days) - 1) . ' days'));

        // 不指定列，规避查询器编译列名不加反引号而触发的保留字问题
        $rows = DB::table('generation_records')
            ->where('user_id', $userId)
            ->where('created_at', $start, '>=')
            ->orderBy('id', 'ASC')
            ->all();

        $byDay = [];
        foreach ($rows as $r) {
            $d = substr((string)$r['created_at'], 0, 10);
            if (!isset($byDay[$d])) {
                $byDay[$d] = ['total' => 0, 'success' => 0, 'failed' => 0, 'points' => 0];
            }
            $byDay[$d]['total']++;
            $st = (string)$r['status'];
            if ($st === 'success') {
                $byDay[$d]['success']++;
            } elseif ($st === 'failed') {
                $byDay[$d]['failed']++;
            }
            $byDay[$d]['points'] += (int)($r['cost_points'] ?? 0);
        }

        // 从首个有数据的日期（不早于窗口起点）到今天，生成连续日期序列
        $today = date('Y-m-d');
        $from = $rows ? substr((string)$rows[0]['created_at'], 0, 10) : $today;
        $windowStart = date('Y-m-d', strtotime($start));
        if ($from < $windowStart) {
            $from = $windowStart;
        }
        $series = [];
        for ($t = strtotime($from); $t <= strtotime($today); $t = strtotime('+1 day', $t)) {
            $d = date('Y-m-d', $t);
            $series[] = isset($byDay[$d])
                ? ['date' => $d, 'total' => $byDay[$d]['total'], 'success' => $byDay[$d]['success'], 'failed' => $byDay[$d]['failed'], 'points' => $byDay[$d]['points']]
                : ['date' => $d, 'total' => 0, 'success' => 0, 'failed' => 0, 'points' => 0];
        }
        return $series;
    }

    /**
     * 我的生成记录（支持任务ID/失败原因关键词、状态筛选）
     *
     * @param array{keyword?:string,status?:string} $filters
     */
    public static function myGenerations(int $userId, int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $q = DB::table('generation_records')->where('user_id', $userId);

        $status = (string)($filters['status'] ?? '');
        $allowStatus = ['pending','processing','success','failed'];
        if ($status !== '' && in_array($status, $allowStatus, true)) {
            $q->where('status', $status);
        }
        $kw = trim((string)($filters['keyword'] ?? ''));
        if ($kw !== '') {
            if (ctype_digit($kw)) {
                // 纯数字按任务 ID 精确匹配，否则搜失败原因
                $q->whereRaw('(`id` = :gid OR `error_msg` LIKE :gkw)', [
                    ':gid' => (int)$kw,
                    ':gkw' => '%' . $kw . '%',
                ]);
            } else {
                $q->where('error_msg', '%' . $kw . '%', 'LIKE');
            }
        }

        return $q->orderBy('id', 'DESC')->paginate($page, $perPage);
    }

    /**
     * 我的订单（支持订单号/商品名关键词、状态筛选）
     *
     * @param array{keyword?:string,status?:string} $filters
     */
    public static function myOrders(int $userId, int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $q = DB::table('orders')->where('user_id', $userId);

        $status = (string)($filters['status'] ?? '');
        $allowStatus = ['pending','paid','refunded','closed'];
        if ($status !== '' && in_array($status, $allowStatus, true)) {
            $q->where('status', $status);
        }
        $kw = trim((string)($filters['keyword'] ?? ''));
        if ($kw !== '') {
            $q->whereRaw('(`order_no` LIKE :okw OR `product_name` LIKE :pkw)', [
                ':okw' => '%' . $kw . '%',
                ':pkw' => '%' . $kw . '%',
            ]);
        }

        return $q->orderBy('id', 'DESC')->paginate($page, $perPage);
    }

    /**
     * 充值套餐列表（上架的）
     */
    public static function rechargeProducts(): array
    {
        return DB::table('point_products')
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort', 'ASC')
            ->all();
    }

    /**
     * 首页/分类页可用筛选维度（风格/颜色/形状），带 10 分钟缓存
     */
    const DIMS_CACHE_KEY = 'dims:styles_colors_shapes';

    public static function dimensionOptions(): array
    {
        $cached = RedisClient::get(self::DIMS_CACHE_KEY);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $dims = [
            'styles'  => DB::table('styles')->where('status', 1)->orderBy('sort', 'ASC')->all(),
            'colors'  => DB::table('colors')->where('status', 1)->orderBy('sort', 'ASC')->all(),
            'shapes'  => DB::table('shapes')->where('status', 1)->orderBy('sort', 'ASC')->all(),
        ];
        RedisClient::set(self::DIMS_CACHE_KEY, $dims, 600);
        return $dims;
    }

    /**
     * 后台修改风格/颜色/形状（价格、提示词、后缀、状态等）后调用，立即生效
     */
    public static function flushDimensionCache(): void
    {
        RedisClient::del(self::DIMS_CACHE_KEY);
    }

    /**
     * 统计：用户头像数 + 生成数
     */
    public static function stats(int $userId): array
    {
        $avatars = DB::table('avatars')->where('user_id', $userId)->where('status', 'success')->count();
        $gens = DB::table('generation_records')->where('user_id', $userId)->count();
        $keys = DB::table('user_keys')->where('user_id', $userId)->whereNull('deleted_at')->count();
        return [
            'avatar_count'  => $avatars,
            'gen_count'      => $gens,
            'key_count'      => $keys,
        ];
    }
}
