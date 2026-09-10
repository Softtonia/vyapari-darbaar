<?php

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
        // 1. notification_templates
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 100)->unique();
            $table->string('title', 200);
            $table->text('body');
            $table->string('image_url', 2048)->nullable();
            $table->string('click_url', 2048)->nullable();
            $table->json('data_json')->nullable();
            $table->string('channel', 30);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        // 2. notification_topics
        Schema::create('notification_topics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        // 3. notification_topic_users
        Schema::create('notification_topic_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_topic_id')->constrained('notification_topics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['notification_topic_id', 'user_id']);
            $table->index('user_id');
        });

        // 4. notification_batches
        Schema::create('notification_batches', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('parent_batch_id')->nullable()->constrained('notification_batches')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('notification_topics')->nullOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('image_url', 2048)->nullable();
            $table->string('click_url', 2048)->nullable();
            $table->json('data_json')->nullable();
            $table->string('notification_type', 30);
            $table->string('audience_type', 30);
            $table->unsignedBigInteger('target_count')->default(0);
            $table->unsignedBigInteger('processed_count')->default(0);
            $table->unsignedBigInteger('success_count')->default(0);
            $table->unsignedBigInteger('partial_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->unsignedBigInteger('skipped_count')->default(0);
            $table->string('status', 30)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['created_by', 'created_at']);
            $table->index('parent_batch_id');
        });

        // 5. notification_batch_users
        Schema::create('notification_batch_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_batch_id')->constrained('notification_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->timestamps();

            $table->unique(['notification_batch_id', 'user_id']);
            $table->index('user_id');
            $table->index('status');
        });

        // 6. in_app_notifications
        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('notification_batch_id')->nullable()->constrained('notification_batches')->nullOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('image_url', 2048)->nullable();
            $table->string('click_url', 2048)->nullable();
            $table->json('data_json')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['notification_batch_id', 'user_id']);
        });

        // 7. notification_logs
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->char('dedupe_key', 64)->nullable()->unique();
            $table->foreignId('notification_batch_id')->nullable()->constrained('notification_batches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('notification_device_id')->nullable()->constrained('notification_devices')->nullOnDelete();
            $table->string('channel', 30);
            $table->string('title', 200);
            $table->text('body');
            $table->json('data_json')->nullable();
            $table->string('status', 30);
            $table->string('provider_message_id', 255)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['notification_batch_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('notification_batch_users');
        Schema::dropIfExists('notification_batches');
        Schema::dropIfExists('notification_topic_users');
        Schema::dropIfExists('notification_topics');
        Schema::dropIfExists('notification_templates');
    }
};
