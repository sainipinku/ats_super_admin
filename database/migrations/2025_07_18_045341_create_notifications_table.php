<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('notifications');

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('type')->comment('admin,super_admin,doer,etc');
            $table->foreignId(User::class);
            $table->foreignId('from_id')->nullable();
            $table->string('source_type')->nullable();
            $table->foreignId('source_id')->nullable();
            $table->text('title')->nullable();
            $table->text('content')->nullable();
            $table->json('firebase')->nullable();
            $table->json('extra')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
