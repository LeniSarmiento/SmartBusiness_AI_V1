<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('title', 120)->nullable();
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'user_id', 'archived_at', 'updated_at'], 'ai_conversations_owner_index');
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('status', 20)->default('completed');
            $table->text('content');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['conversation_id', 'status', 'id']);
        });

        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->string('provider', 50)->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_input_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->string('status', 20);
            $table->string('error_class', 255)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['conversation_id', 'created_at']);
        });

        DB::statement("ALTER TABLE ai_messages ADD CONSTRAINT ai_messages_role_check CHECK (role IN ('user', 'assistant'))");
        DB::statement("ALTER TABLE ai_messages ADD CONSTRAINT ai_messages_status_check CHECK (status IN ('pending', 'completed', 'failed'))");
        DB::statement("ALTER TABLE ai_usage_records ADD CONSTRAINT ai_usage_status_check CHECK (status IN ('completed', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
