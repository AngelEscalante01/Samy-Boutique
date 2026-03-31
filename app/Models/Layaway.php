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
        'vigencia_dias',
        'fecha_vencimiento',
        'liquidated_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'vigencia_dias' => 'integer',
        'fecha_vencimiento' => 'date',
        'liquidated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = ['balance', 'estado_vigencia'];

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

    public function getBalanceAttribute(): string
    {
        $subtotal = (string) $this->subtotal;
        $paid = (string) $this->paid_total;

        return bcsub($subtotal, $paid, 2);
    }

    public function getEstadoVigenciaAttribute(): string
    {
        if (! $this->fecha_vencimiento instanceof CarbonInterface) {
            return 'sin_vigencia';
        }

        $today = now()->startOfDay();
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
