<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->text('content');

            // Deduplication happens on this, not on the content itself, so a
            // repeated copy of a large clip costs one index lookup.
            $table->string('fingerprint', 64);

            $table->string('kind', 16)->default('text');
            $table->unsignedInteger('byte_size');
            $table->boolean('pinned')->default(false);
            $table->unsignedInteger('times_copied')->default(1);
            $table->timestamp('first_copied_at');
            $table->timestamp('last_copied_at');

            $table->unique('fingerprint');

            // Serves the only hot query: pinned first, then most recent.
            $table->index(['pinned', 'last_copied_at'], 'clips_history_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        /** @var string $table */
        $table = config('clipboard.table', 'clips');

        return $table;
    }
};
