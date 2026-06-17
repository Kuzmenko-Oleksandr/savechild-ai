<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('captured_on')->unique();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('low')->default(0);
            $table->unsignedInteger('medium')->default(0);
            $table->unsignedInteger('high')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_snapshots');
    }
};
