<?php

use App\Enums\WorkflowMasterType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin cannot be added to workflow master', function () {
    $this->signIn();

    $admin = User::factory()
        ->admin()
        ->create();

    $this->post(
        route('workflow-settings.store'),
        [
            'type' => WorkflowMasterType::Approver->value,
            'target_user_id' => $admin->id,
        ]
    )->assertSessionHasErrors(
        'target_user_id'
    );
});

test('admin is not displayed in workflow master user selector', function (string $type) {
    $this->signIn();

    User::factory()
        ->admin()
        ->create([
            'name' => 'Hidden Admin',
        ]);

    User::factory()
        ->create([
            'name' => 'Visible User',
        ]);

    $this->get(
        route('workflow-settings.group', ['type' => $type])
    )
        ->assertOk()
        ->assertSee('Visible User')
        ->assertDontSee('Hidden Admin');
})->with(['signer', 'destination', 'approver']);
