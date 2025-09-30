<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    : void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // required
            $table->integer('percentage');
            $table->boolean('active')->default(true);
            $table->integer('usage_cap')->nullable();
            $table->integer('stack_order')->nullable();
            $table->timestamps();
        });

        Schema::create('user_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->integer('usage_count')->default(0);
            $table->integer('usage_limit')->nullable();
            $table->timestamps();
        });

        Schema::create('discount_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->enum('action', ['assigned', 'revoked', 'applied']);
            $table->timestamps();
        });
    }

    public function down()
    : void
    {
        Schema::dropIfExists('discount_audits');
        Schema::dropIfExists('user_discounts');
        Schema::dropIfExists('discounts');
    }
};
