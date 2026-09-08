<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class UserKey extends Model
{
    protected string $table = 'user_keys';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id', 'api_key', 'api_key_hash', 'name', 'callback_url',
        'status', 'rate_limit', 'daily_limit', 'used_today', 'last_used_at', 'deleted_at',
    ];

    /**
     * 根据明文 API Key 查询
     */
    public static function findByApiKey(string $apiKey): ?static
    {
        $hash = hash('sha256', $apiKey);
        $row = static::query()->where('api_key_hash', $hash)->first();
        return $row ? static::newInstanceFromRow($row) : null;
    }

    public function isActive(): bool
    {
        return (int)($this->attributes['status'] ?? 0) === 1;
    }

    /**
     * 标记今日使用 +1
     */
    public function markUsed(): void
    {
        $this->attributes['used_today'] = ((int)($this->attributes['used_today'] ?? 0)) + 1;
        $this->attributes['last_used_at'] = date('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * 是否允许调用（日限额）
     */
    public function withinDailyLimit(): bool
    {
        $limit = (int)($this->attributes['daily_limit'] ?? 0);
        if ($limit === 0) {
            return true;
        }
        return (int)($this->attributes['used_today'] ?? 0) < $limit;
    }
}
