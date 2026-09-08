<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class GenerationRecord extends Model
{
    protected string $table = 'generation_records';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id', 'key_id', 'sub_user_id', 'avatar_id', 'prompt_id',
        'style_id', 'color_id', 'shape_id', 'params', 'origin_image_url',
        'cost_points',
        'api_model', 'api_request_id', 'api_response', 'status',
        'error_msg', 'retry_count',
    ];
}
