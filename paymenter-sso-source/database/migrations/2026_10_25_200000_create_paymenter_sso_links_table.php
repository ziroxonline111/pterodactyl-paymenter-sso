<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which Paymenter account signs in to which panel account, recorded at the first sign-in (matched by the
        // verified email address), so that a later change of email address in Paymenter does not lock anyone out.
        Schema::create('paymenter_sso_links', function (Blueprint $table): void {
            $table->unsignedInteger('user_id')->primary();
            // The Paymenter URL from the settings at the time, so that the ids of another Paymenter never match.
            $table->string('paymenter_url', 255);
            $table->unsignedBigInteger('paymenter_user_id');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['paymenter_url', 'paymenter_user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paymenter_sso_links');
    }
};
