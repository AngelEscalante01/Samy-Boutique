<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Layaway extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'created_by',
        'sale_id',
        'status',
        'subtotal',
        'paid_total',
        'liquidated_at',
        'cancelled_at',
        'fecha_vencimiento',
        'vigencia_dias',
        'observaciones',
        'global_discount_type',
        'global_discount_value',
        'manual_discount_total',
        'refunded_total',
        'version',
        'delivered_at',
        'delivery_tracking_enabled',
        'loyalty_discount_total',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'liquidated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'fecha_vencimiento' => 'date:Y-m-d',
        'vigencia_dias' => 'integer',
        'global_discount_value' => 'decimal:2',
        'manual_discount_total' => 'decimal:2',
        'refunded_total' => 'decimal:2',
        'version' => 'integer',
        'delivered_at' => 'datetime',
        'delivery_tracking_enabled' => 'boolean',
        'loyalty_discount_total' => 'decimal:2',
    ];

    protected $appends = ['total', 'applied_total', 'balance', 'excess', 'estado_vigencia'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LayawayItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LayawayPayment::class)->orderBy('created_at');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(LayawayRefund::class)->orderBy('refunded_at');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(LayawayAdjustment::class)->orderBy('created_at');
    }

    public function getTotalAttribute(): string
    {
        return bcsub(bcsub((string) $this->subtotal, (string) ($this->manual_discount_total ?? '0'), 2), (string) ($this->loyalty_discount_total ?? '0'), 2);
    }

    public function getAppliedTotalAttribute(): string
    {
        return bcsub((string) $this->paid_total, (string) ($this->refunded_total ?? '0'), 2);
    }

    public function getBalanceAttribute(): string
    {
        return bccomp($this->total, $this->applied_total, 2) > 0 ? bcsub($this->total, $this->applied_total, 2) : '0.00';
    }

    public function getExcessAttribute(): string
    {
        $target = $this->status === 'cancelled' ? '0.00' : $this->total;

        return bccomp($this->applied_total, $target, 2) > 0 ? bcsub($this->applied_total, $target, 2) : '0.00';
    }

    public function getEstadoVigenciaAttribute(): string
    {
        if (! $this->fecha_vencimiento instanceof CarbonInterface) {
            return 'sin_vigencia';
        }

        $today = now((string) config('firebase.layaway_timezone', 'America/Mexico_City'))->startOfDay();
        $dueDate = $this->fecha_vencimiento->copy()->startOfDay();

        if ($dueDate->lt($today)) {
            return 'vencido';
        }
        if ($dueDate->equalTo($today)) {
            return 'vence_hoy';
        }

        return 'vigente';
    }
}
