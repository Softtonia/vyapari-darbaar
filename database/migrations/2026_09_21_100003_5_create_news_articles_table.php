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
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_source_id')->constrained('news_sources')->restrictOnDelete();
            $table->foreignId('news_category_id')->constrained('news_categories')->restrictOnDelete();
            $table->string('content_type', 32)->default('news');
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('short_description')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image', 500)->nullable();
            $table->string('author_name', 150)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->date('published_on')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_breaking')->default(false);
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->json('meta_keywords')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author', 255)->nullable();
            $table->boolean('is_imported')->default(false);
            $table->foreignId('import_run_id')->nullable()->constrained('news_import_runs')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at', 'id'], 'na_status_pub_id_idx');
            $table->index(['news_source_id', 'status', 'published_at', 'id'], 'na_src_status_pub_id_idx');
            $table->index(['news_category_id', 'status', 'published_at', 'id'], 'na_cat_status_pub_id_idx');
            $table->index(['content_type', 'status', 'published_at', 'id'], 'na_type_status_pub_id_idx');
            $table->index(['is_featured', 'status', 'published_at', 'id'], 'na_feat_status_pub_id_idx');
            $table->index(['is_breaking', 'status', 'published_at', 'id'], 'na_brk_status_pub_id_idx');
            $table->index(['status', 'scheduled_at', 'id'], 'na_status_sched_id_idx');
            $table->index(['published_on', 'id'], 'na_pub_on_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};
