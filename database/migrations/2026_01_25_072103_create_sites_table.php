<?php

use App\Enums\SiteStatus;
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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('hosting_id')->constrained()->cascadeOnDelete();
            $table->string('name')->index();
            $table->string('domain')->index();
            $table->string('directory');
            $table->string('email_username');
            $table->text('email_password');
            $table->string('database_name');
            $table->string('database_user');
            $table->text('database_pass');
            $table->string('status')->default(SiteStatus::PENDING);
            $table->timestamps();

            $table->unique(['hosting_id', 'domain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
