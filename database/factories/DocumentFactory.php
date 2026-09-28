<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'requires_pdf_workflow' => false,
            'document_number' => null,
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'status' => DocumentStatus::Draft,
        ];
    }

    public function submitted(): static
    {
        return $this->state(
            fn (array $attributes) => [
                'status' => DocumentStatus::Submitted,
                'submitted_at' => now(),
            ]
        );
    }

    public function waitingApproval(): static
    {
        return $this->state(
            fn (array $attributes) => [
                'status' => DocumentStatus::WaitingApproval,
                'submitted_at' => now()->subMinutes(2),
                'approver_assigned_at' => now()->subMinute(),
            ]
        );
    }

    public function approved(): static
    {
        return $this->state(
            fn (array $attributes) => [
                'status' => DocumentStatus::Approved,
                'submitted_at' => now()->subMinutes(3),
                'approver_assigned_at' => now()->subMinutes(2),
                'approved_at' => now(),
            ]
        );
    }

    public function rejected(
        string $reason =
            'Dokumen perlu diperbaiki sebelum dapat disetujui.'
    ): static {
        return $this->state(
            fn (array $attributes) => [
                'status' => DocumentStatus::Rejected,
                'submitted_at' => now()->subMinutes(3),
                'approver_assigned_at' => now()->subMinutes(2),
                'approved_at' => null,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]
        );
    }

    public function waitingSignature(): static
    {
        return $this->state(
            fn (array $attributes) => [
                'status' => DocumentStatus::WaitingSignature,
                'submitted_at' => now()->subMinutes(5),
                'approver_assigned_at' => now()->subMinutes(4),
                'approved_at' => now()->subMinutes(2),
                'signer_assigned_at' => now(),
            ]
        );
    }
}
