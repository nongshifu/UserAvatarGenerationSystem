<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Shape extends Model
{
    protected string $table = 'shapes';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'prompt_suffix', 'icon', 'sort', 'status'];
}
