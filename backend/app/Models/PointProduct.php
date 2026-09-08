<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PointProduct extends Model
{
    protected string $table = 'point_products';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'price', 'points', 'bonus_points', 'sort', 'status', 'description', 'deleted_at',
    ];
}
