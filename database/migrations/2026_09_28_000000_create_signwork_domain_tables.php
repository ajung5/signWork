<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('destination_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number')->nullable()->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approver_assigned_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('signer_assigned_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('revision_count')->default(0);
            $table->timestamp('last_revised_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('workflow_cycle')->default(0);
            $table->boolean('requires_pdf_workflow')->default(false);
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['destination_user_id', 'sent_at']);
        });

        Schema::create('document_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->string('document_number')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('qr_mode')->default('per_signer');
            $table->unsignedInteger('specimen_version')->default(1);
            $table->string('original_path')->nullable();
            $table->char('original_sha256', 64)->nullable();
            $table->json('pdf_metadata')->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_name')->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->string('prepared_path')->nullable();
            $table->char('prepared_sha256', 64)->nullable();
            $table->string('current_path')->nullable();
            $table->char('current_sha256', 64)->nullable();
            $table->char('final_sha256', 64)->nullable();
            $table->timestamp('positions_confirmed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'number']);
            $table->index(['document_id', 'status']);
        });

        Schema::create('document_approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_cycle_id')->constrained('document_cycles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default('pending')->index();
            $table->string('name_snapshot');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['document_cycle_id', 'sequence']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('document_signature_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_cycle_id')->constrained('document_cycles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default('pending')->index();
            $table->string('name_snapshot');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->json('profile_snapshot')->nullable();
            $table->string('placeholder')->nullable();
            $table->unsignedInteger('page')->nullable();
            $table->decimal('x', 10, 3)->nullable();
            $table->decimal('y', 10, 3)->nullable();
            $table->decimal('width', 10, 3)->nullable();
            $table->decimal('height', 10, 3)->nullable();
            $table->string('placement_source')->nullable();
            $table->string('specimen_format')->default('qr_2cm');
            $table->string('specimen_scope')->default('selected_page');
            $table->json('specimen_pages')->nullable();
            $table->char('input_sha256', 64)->nullable();
            $table->char('output_sha256', 64)->nullable();
            $table->string('output_path')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->timestamps();

            $table->unique(['document_cycle_id', 'sequence']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('workflow_master_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'type', 'target_user_id']);
            $table->index(['user_id', 'type', 'is_default']);
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event')->index();
            $table->string('outcome')->default('success')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->uuid('document_uuid')->nullable()->index();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('workflow_master_entries');
        Schema::dropIfExists('document_signature_steps');
        Schema::dropIfExists('document_approval_steps');
        Schema::dropIfExists('document_cycles');
        Schema::dropIfExists('documents');
    }
};
