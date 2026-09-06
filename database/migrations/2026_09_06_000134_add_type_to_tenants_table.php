<?php

use App\Constants\TenantType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Bestehende Tenants sind Betreiber - Kaeufer entstehen erst durch
            // die Registrierung ueber den Marktplatz (siehe App\Constants\TenantType).
            $table->string('type')
                ->default(TenantType::OPERATOR->value)
                ->after('name')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
