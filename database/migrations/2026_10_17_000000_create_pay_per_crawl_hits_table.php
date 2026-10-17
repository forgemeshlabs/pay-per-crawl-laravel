<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_per_crawl_hits', function (Blueprint $table) {
            $table->id();
            $table->dateTime('ts')->index();
            $table->string('ua_class', 16)->index();
            $table->string('bot_name', 64)->default('')->index();
            $table->boolean('verified')->nullable();
            $table->string('method', 8)->default('GET');
            $table->string('path', 255)->default('/');
            $table->string('status', 16)->index();
            $table->string('rail', 8)->default('');
            $table->unsignedBigInteger('amount_units')->default(0);
            $table->string('tx_hash', 80)->default('');
            $table->string('payer', 48)->default('');
            $table->string('reason', 64)->default('');
            $table->char('ip_hash', 16)->default('');
            $table->string('ua', 255)->default('');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_per_crawl_hits');
    }
};
