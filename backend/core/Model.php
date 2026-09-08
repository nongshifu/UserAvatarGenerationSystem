<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 模型基类
 * - 静态方法：find / where / all / create
 * - 实例方法：save / delete
 * - 自动维护 created_at / updated_at（子类需声明 $timestamps = true）
 *
 * 子类需定义：
 *   protected string $table = 'users';
 *   protected string $primaryKey = 'id';
 *   protected array $fillable = [...];  // 允许批量赋值的字段
 */
abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    /** @var string[] 允许批量赋值的字段 */
    protected array $fillable = [];
    /** @var bool 是否维护 created_at/updated_at */
    protected bool $timestamps = true;

    /** @var array<string,mixed> 当前实例属性 */
    protected array $attributes = [];
    /** @var bool 是否已存在（来自 DB） */
    private bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * 批量赋值（仅 $fillable 内字段）
     */
    public function fill(array $data): self
    {
        foreach ($data as $k => $v) {
            if (in_array($k, $this->fillable, true)) {
                $this->attributes[$k] = $v;
            }
        }
        return $this;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    /** 魔术访问 */
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /** 主键值 */
    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    /**
     * 保存（插入或更新）
     */
    public function save(): bool
    {
        $data = $this->attributes;
        // 时间戳
        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!$this->exists) {
                $data['created_at'] = $data['created_at'] ?? $now;
            }
            $data['updated_at'] = $now;
        }
        if ($this->exists) {
            $id = $this->getKey();
            if ($id === null) {
                return false;
            }
            DB::table($this->table)
                ->where($this->primaryKey, $id)
                ->update($data);
            return true;
        }
        $id = DB::table($this->table)->insert($data);
        $this->attributes[$this->primaryKey] = $id;
        $this->exists = true;
        return true;
    }

    /**
     * 删除
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        $id = $this->getKey();
        if ($id === null) {
            return false;
        }
        return DB::table($this->table)
            ->where($this->primaryKey, $id)
            ->delete() > 0;
    }

    // ── 静态查询 ───────────────────────────────────────

    public static function query(): DB
    {
        $instance = new static();
        return DB::table($instance->table);
    }

    public static function find(int|string $id): ?static
    {
        $instance = new static();
        $row = DB::table($instance->table)
            ->where($instance->primaryKey, $id)
            ->first();
        return $row ? static::newInstanceFromRow($row) : null;
    }

    public static function where(string $column, mixed $value, string $operator = '='): DB
    {
        return static::query()->where($column, $value, $operator);
    }

    public static function all(): array
    {
        $rows = static::query()->all();
        $out = [];
        foreach ($rows as $row) {
            $out[] = static::newInstanceFromRow($row);
        }
        return $out;
    }

    /**
     * 创建一条记录
     */
    public static function create(array $data): static
    {
        $instance = new static();
        $instance->fill($data);
        $instance->save();
        return $instance;
    }

    protected static function newInstanceFromRow(array $row): static
    {
        $instance = new static();
        $instance->attributes = $row;
        $instance->exists     = true;
        return $instance;
    }

    public function isExists(): bool
    {
        return $this->exists;
    }
}
