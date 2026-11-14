<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bios are public unless the member hides theirs in Profile. Existing members (including the imported ones)
 * never chose to hide it (it was simply off by default), so their bios are made public too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_bio_public')->default(true)->change();
        });

        DB::table('users')->update(['is_bio_public' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_bio_public')->default(false)->change();
        });
    }
};
