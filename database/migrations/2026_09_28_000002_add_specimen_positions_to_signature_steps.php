<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signature_steps', function (Blueprint $table): void {
            $table->json('specimen_positions')->nullable()->after('specimen_pages');
        });
    }

    public function down(): void
    {
        Schema::table('document_signature_steps', function (Blueprint $table): void {
            $table->dropColumn('specimen_positions');
        });
    }
};
