<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Color extends Model
{
    protected string $table = 'colors';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'hex', 'prompt_suffix', 'sort', 'status'];
}
