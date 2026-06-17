<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->string('type')->default('risk_elevated');
            $table->string('title');
            $table->text('body');
            // status: check_it_out | in_progress | resolved
            $table->string('status')->default('check_it_out')->index();
            $table->json('attachments')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['child_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_notifications');
    }
};
