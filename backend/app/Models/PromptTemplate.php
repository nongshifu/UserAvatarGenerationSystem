<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PromptTemplate extends Model
{
    protected string $table = 'prompt_templates';
    protected string $primaryKey = 'id';
    protected array $fillable = ['title', 'prompt', 'category', 'is_default', 'sort', 'status'];

    /**
     * 取默认提示词
     */
    public static function getDefault(): ?static
    {
        $row = static::query()->where('is_default', 1)->where('status', 1)->first();
        return $row ? static::newInstanceFromRow($row) : null;
    }
}
