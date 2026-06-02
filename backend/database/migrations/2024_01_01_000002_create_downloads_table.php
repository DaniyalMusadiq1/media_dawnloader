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
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('playlist_download_id')->nullable()->constrained('playlist_downloads')->nullOnDelete();
            $table->string('original_url')->index();
            $table->string('title');
            $table->enum('type', ['audio', 'video'])->default('video');
            $table->string('quality')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->integer('duration')->nullable()->comment('Duration in seconds');
            $table->string('thumbnail')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'scheduled'])->default('pending');
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->string('file_path')->nullable();
            $table->string('download_url')->nullable();
            $table->string('share_token')->unique()->index()->comment('Short token for public sharing');
            $table->integer('progress')->default(0)->comment('Download progress percentage');
            $table->string('speed')->nullable()->comment('Current download speed');
            $table->integer('eta')->nullable()->comment('Estimated time remaining in seconds');
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};
