<?php

use App\Enums\HostingProvider;
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
        Schema::table('hostings', function (Blueprint $table) {
            $table->string('provider')->default(HostingProvider::Cpanel)->after('organization_id');
            $table->string('ip')->nullable()->after('server_id');
            $table->integer('ftp_port')->nullable()->after('token');
            $table->integer('ssh_port')->nullable()->after('ftp_port');
            $table->foreignId('server_id')->nullable()->change();
            $table->string('domain')->nullable()->change();
            $table->string('username')->nullable()->change();
            $table->text('password')->nullable()->change();
            $table->text('token')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hostings', function (Blueprint $table) {
            $table->dropColumn(['ip', 'ftp_port', 'ssh_port']);
            $table->foreignId('server_id')->nullable(false)->change();
        });
    }
};
