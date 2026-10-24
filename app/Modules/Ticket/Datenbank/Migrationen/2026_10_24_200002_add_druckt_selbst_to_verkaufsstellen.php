<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** E128: Verkaufsstelle druckt ihre Karten selbst beim Verkauf. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verkaufsstellen', function (Blueprint $table) {
            $table->boolean('druckt_selbst')->default(false)->after('aktiv');
        });
    }

    public function down(): void
    {
        Schema::table('verkaufsstellen', function (Blueprint $table) {
            $table->dropColumn('druckt_selbst');
        });
    }
};
