<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layaways', function (Blueprint $table) {
            $table->unsignedInteger('vigencia_dias')->nullable()->after('paid_total');
            $table->date('fecha_vencimiento')->nullable()->after('vigencia_dias')->index();
        });
    }

    public function down(): void
    {
        Schema::table('layaways', function (Blueprint $table) {
            $table->dropColumn(['vigencia_dias', 'fecha_vencimiento']);
        });
    }
};
