<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class SiteSetting extends Model
{
    protected string $table = 'site_settings';
    protected string $primaryKey = 'id';
    protected array $fillable = ['group_key', 'item_key', 'item_value', 'item_type', 'description'];

    /**
     * 按 group_key 读取整组配置
     */
    public static function loadGroup(string $group): array
    {
        $rows = static::query()->where('group_key', $group)->all();
        $out  = [];
        foreach ($rows as $row) {
            $arr = $row->toArray();
            $out[$arr['item_key']] = $arr['item_value'];
        }
        return $out;
    }

    /**
     * 设置（不存在则插入）
     */
    public static function set(string $group, string $key, string $value, string $type = 'string'): void
    {
        $row = static::query()->where('group_key', $group)->where('item_key', $key)->first();
        if ($row) {
            // $row 是数组（DB::first 返回）
            $instance = static::find((int)$row['id']);
            if ($instance) {
                $instance->setAttribute('item_value', $value);
                $instance->setAttribute('item_type', $type);
                $instance->save();
            }
            return;
        }
        static::create([
            'group_key'  => $group,
            'item_key'   => $key,
            'item_value' => $value,
            'item_type'  => $type,
        ]);
    }
}
