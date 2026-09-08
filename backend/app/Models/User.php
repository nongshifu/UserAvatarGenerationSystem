<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'username', 'email', 'phone', 'phone_verified', 'password', 'nickname', 'avatar',
        'role', 'status', 'points', 'total_recharge', 'total_consume',
        'email_verified_at', 'remember_token', 'deleted_at',
    ];

    /**
     * 校验密码（bcrypt）
     */
    public function checkPassword(string $password): bool
    {
        $hash = (string)($this->attributes['password'] ?? '');
        return password_verify($password, $hash);
    }

    /**
     * 是否启用
     */
    public function isActive(): bool
    {
        return (int)($this->attributes['status'] ?? 0) === 1;
    }

    public function isAdmin(): bool
    {
        return ($this->attributes['role'] ?? '') === 'admin';
    }
}
