<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'created_by',
        'subtotal',
        'discount_total',
        'coupon_discount_total',
        'loyalty_discount_total',
        'loyalty_applied',
        'total',
        'global_discount_type',
        'global_discount_value',
        'coupon_code',
        'coupon_id',
        'status',
        'cancellation_reason',
        'canceled_at',
        'canceled_by',
        'cancel_reason',
        'cancel_type',
        'inventory_action',
        'return_condition',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'coupon_discount_total' => 'decimal:2',
        'loyalty_discount_total' => 'decimal:2',
        'loyalty_applied' => 'boolean',
        'total' => 'decimal:2',
        'global_discount_value' => 'decimal:2',
        'canceled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function canceledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'canceled_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
