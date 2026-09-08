<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class SeoSetting extends Model
{
    protected string $table = 'seo_settings';
    protected string $primaryKey = 'id';
    protected array $fillable = ['page', 'title', 'keywords', 'description', 'og_image', 'canonical'];

    public static function findByPage(string $page): ?static
    {
        $row = static::query()->where('page', $page)->first();
        return $row ? static::newInstanceFromRow($row) : null;
    }
}
