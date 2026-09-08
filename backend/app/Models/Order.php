<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Order extends Model
{
    protected string $table = 'orders';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'order_no', 'user_id', 'product_id', 'product_name', 'amount',
        'points', 'status', 'pay_method', 'pay_trade_no', 'paid_at',
        'refunded_at', 'admin_remark',
    ];

    /**
     * 生成订单号（20位）
     */
    public static function genOrderNo(): string
    {
        return date('YmdHis') . str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
