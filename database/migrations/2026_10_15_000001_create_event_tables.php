<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('theme')->nullable();
            $table->string('location')->nullable();
            $table->date('event_date');
            $table->string('start_time', 5);
            $table->unsignedInteger('duration_minutes');
            $table->boolean('ishoma_enabled')->default(true);
            $table->unsignedSmallInteger('ishoma_minutes')->default(60);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title');
            $table->string('type', 20)->default('sesi');
            $table->string('start_time', 5);
            $table->unsignedInteger('duration_minutes');
            $table->string('pic')->nullable();
            $table->string('note')->nullable();
            $table->boolean('is_ishoma')->default(false);
            $table->timestamps();
            $table->index(['event_id', 'position']);
        });

        Schema::create('speakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // sambutan | pemateri
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('topic')->nullable();
            $table->timestamps();
        });

        Schema::create('scripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // sambutan | pidato | mc | doa
            $table->longText('content');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_ai')->default(true);
            $table->timestamps();
            $table->unique(['event_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scripts');
        Schema::dropIfExists('speakers');
        Schema::dropIfExists('event_sessions');
        Schema::dropIfExists('events');
    }
};
