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
            $table->string('name');
            $table->decimal('percentage', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });


        Schema::create('user_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('discount_id');

            $table->unsignedInteger('usage_cap')->nullable();   // ❗ REQUIRED
            $table->unsignedInteger('usage_count')->default(0); // ❗ REQUIRED

            $table->boolean('revoked')->default(false);
            $table->timestamps();
        });


        Schema::create('discount_audits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('discount_id')->nullable();

            $table->string('action'); // assigned | revoked | applied
            $table->decimal('amount_before', 10, 2)->nullable();
            $table->decimal('amount_after', 10, 2)->nullable();

            $table->json('meta')->nullable(); // extra context
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
