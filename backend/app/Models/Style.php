<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Style extends Model
{
    protected string $table = 'styles';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'prompt_suffix', 'prompt', 'icon', 'cost_points', 'sort', 'status'];
}
