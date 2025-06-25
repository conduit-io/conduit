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
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('package');
            $table->text('description')->nullable();
            $table->json('commands')->nullable();
            $table->json('env_vars')->nullable();
            $table->json('service_providers')->nullable();
            $table->json('topics')->nullable();
            $table->string('url')->nullable();
            $table->integer('stars')->default(0);
            $table->string('version')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('installed_at');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider_class');
            $table->string('component_name');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('component_name')->references('name')->on('components')->onDelete('cascade');
            $table->unique(['provider_class', 'component_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_providers');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('components');
    }
};
