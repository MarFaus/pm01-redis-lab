<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ЛР 50: свободные билеты. В методичке это поле available_tickets таблицы events.
 * Остальные поля методички у нас уже есть под своими именами:
 * title = name, event_date = starts_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('available_tickets')->default(100);
        });

        DB::table('events')->update(['available_tickets' => DB::raw('tickets_total')]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('available_tickets');
        });
    }
};
