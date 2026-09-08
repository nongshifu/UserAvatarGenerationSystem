<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class SubUser extends Model
{
    protected string $table = 'sub_users';
    protected string $primaryKey = 'id';
    protected array $fillable = ['key_id', 'identifier', 'nickname', 'avatar'];

    /**
     * 按 (key_id, identifier) 取或创建子用户
     */
    public static function firstOrCreate(int $keyId, string $identifier): static
    {
        $row = static::query()
            ->where('key_id', $keyId)
            ->where('identifier', $identifier)
            ->first();
        if ($row) {
            return static::newInstanceFromRow($row);
        }
        return static::create([
            'key_id'     => $keyId,
            'identifier' => $identifier,
            'nickname'   => $identifier,
            'avatar'     => '',
        ]);
    }
}
