<?php

use App\Enums\WorkflowMasterType;
use App\Models\User;
use App\Models\WorkflowMasterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can add workflow master entries', function () {
    $owner = $this->signIn();
    $target = User::factory()->create();

    $this->post(
        route('workflow-settings.store'),
        [
            'type' => WorkflowMasterType::Approver->value,
            'target_user_id' => $target->id,
            'is_default' => true,
        ]
    )
        ->assertRedirect(
            route('workflow-settings.group', ['type' => 'approver'])
        )
        ->assertSessionHas('success');

    $entry = WorkflowMasterEntry::query()
        ->where(
            'user_id',
            $owner->id
        )
        ->where(
            'type',
            WorkflowMasterType::Approver->value
        )
        ->where(
            'target_user_id',
            $target->id
        )
        ->firstOrFail();

    expect($entry->is_default)
        ->toBeTrue();
});

test('first entry for a type automatically becomes default', function () {
    $owner = $this->signIn();
    $target = User::factory()->create();

    $this->post(
        route('workflow-settings.store'),
        [
            'type' => WorkflowMasterType::Signer->value,
            'target_user_id' => $target->id,
        ]
    )->assertRedirect();

    expect(
        WorkflowMasterEntry::query()
            ->where(
                'user_id',
                $owner->id
            )
            ->firstOrFail()
            ->is_default
    )->toBeTrue();
});

test('user can edit own workflow master entry', function () {
    $owner = $this->signIn();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $entry = WorkflowMasterEntry::query()
        ->create([
            'user_id' => $owner->id,
            'type' => WorkflowMasterType::Destination,
            'target_user_id' => $first->id,
            'is_default' => true,
        ]);

    $this->put(
        route(
            'workflow-settings.entry.update',
            $entry
        ),
        [
            'type' => WorkflowMasterType::Destination->value,
            'target_user_id' => $second->id,
            'is_default' => true,
        ]
    )->assertRedirect(
        route('workflow-settings.group', ['type' => 'destination'])
    );

    expect(
        $entry->fresh()->target_user_id
    )->toBe($second->id);
});

test('user can delete own workflow master entry', function () {
    $owner = $this->signIn();
    $target = User::factory()->create();

    $entry = WorkflowMasterEntry::query()
        ->create([
            'user_id' => $owner->id,
            'type' => WorkflowMasterType::Approver,
            'target_user_id' => $target->id,
            'is_default' => true,
        ]);

    $this->delete(
        route(
            'workflow-settings.entry.destroy',
            $entry
        )
    )->assertRedirect(
        route('workflow-settings.group', ['type' => 'approver'])
    );

    expect(
        WorkflowMasterEntry::query()
            ->whereKey($entry->id)
            ->exists()
    )->toBeFalse();
});

test('user cannot edit another users workflow master entry', function () {
    $this->signIn();

    $owner = User::factory()->create();
    $target = User::factory()->create();

    $entry = WorkflowMasterEntry::query()
        ->create([
            'user_id' => $owner->id,
            'type' => WorkflowMasterType::Signer,
            'target_user_id' => $target->id,
            'is_default' => true,
        ]);

    $this->get(
        route(
            'workflow-settings.entry.edit',
            $entry
        )
    )->assertForbidden();
});

test('only one default exists for each user and type', function () {
    $owner = $this->signIn();
    $first = User::factory()->create();
    $second = User::factory()->create();

    WorkflowMasterEntry::query()
        ->create([
            'user_id' => $owner->id,
            'type' => WorkflowMasterType::Approver,
            'target_user_id' => $first->id,
            'is_default' => true,
        ]);

    $this->post(
        route('workflow-settings.store'),
        [
            'type' => WorkflowMasterType::Approver->value,
            'target_user_id' => $second->id,
            'is_default' => true,
        ]
    )->assertRedirect();

    expect(
        WorkflowMasterEntry::query()
            ->where(
                'user_id',
                $owner->id
            )
            ->where(
                'type',
                WorkflowMasterType::Approver->value
            )
            ->where(
                'is_default',
                true
            )
            ->count()
    )->toBe(1);

    expect(
        WorkflowMasterEntry::query()
            ->where(
                'user_id',
                $owner->id
            )
            ->where(
                'is_default',
                true
            )
            ->value('target_user_id')
    )->toBe($second->id);
});

test('each master page only shows entries and form for its category', function (string $type) {
    $owner = $this->signIn();
    foreach (['signer', 'destination', 'approver'] as $category) {
        $target = User::factory()->create(['name' => 'Member-'.$category]);
        WorkflowMasterEntry::create(['user_id' => $owner->id, 'type' => $category, 'target_user_id' => $target->id]);
    }

    $response = $this->get(route('workflow-settings.group', ['type' => $type]));
    $response->assertOk()->assertSee('data-master-group="'.$type.'"', false);
    foreach (array_diff(['signer', 'destination', 'approver'], [$type]) as $other) {
        $response->assertDontSee('data-master-group="'.$other.'"', false);
    }
    $this->get(route('workflow-settings.edit'))->assertRedirect(route('workflow-settings.group', ['type' => 'signer']));
})->with(['signer', 'destination', 'approver']);

test('unknown master category is not available', function () {
    $this->signIn();
    $this->get('/master/workflow/group/unknown')->assertNotFound();
});

test('master search matches names and emails within the owner and category', function (string $search) {
    $owner = $this->signIn();
    $target = User::factory()->create(['name' => 'Nama Pilihan', 'email' => 'pilihan@example.test']);
    $otherOwner = User::factory()->create();
    $expected = WorkflowMasterEntry::query()->create([
        'user_id' => $owner->id, 'type' => 'signer', 'target_user_id' => $target->id,
    ]);
    WorkflowMasterEntry::query()->create([
        'user_id' => $otherOwner->id, 'type' => 'signer', 'target_user_id' => $target->id,
    ]);
    WorkflowMasterEntry::query()->create([
        'user_id' => $owner->id, 'type' => 'approver', 'target_user_id' => $target->id,
    ]);

    $this->get(route('workflow-settings.group', ['type' => 'signer', 'q' => $search]))
        ->assertViewHas('entries', fn ($entries) => $entries->total() === 1 && $entries->first()->id === $expected->id);
})->with(['Nama Pilihan', 'pilihan@example.test']);

test('master pagination keeps search and excludes all assigned users from add options', function () {
    $owner = $this->signIn();
    $targets = User::factory()->count(21)->create(['name' => 'Peserta Master']);
    foreach ($targets as $target) {
        WorkflowMasterEntry::query()->create([
            'user_id' => $owner->id, 'type' => 'signer', 'target_user_id' => $target->id,
        ]);
    }
    $available = User::factory()->create();

    $response = $this->get(route('workflow-settings.group', ['type' => 'signer', 'q' => 'Peserta']));
    $response->assertViewHas('entries', fn ($entries) => $entries->total() === 21 && $entries->count() === 20)
        ->assertViewHas('users', fn ($users) => $users->contains('id', $available->id) && $users->whereIn('id', $targets->modelKeys())->isEmpty());
    $this->get($response->viewData('entries')->nextPageUrl())
        ->assertViewHas('search', 'Peserta')
        ->assertViewHas('entries', fn ($entries) => $entries->count() === 1 && $entries->first()->target_user_id === $targets->last()->id);
    $this->get(route('workflow-settings.group', ['type' => 'signer', 'q' => 'TidakCocok']))
        ->assertSee('Tidak ada pengguna yang cocok dengan pencarian.')
        ->assertViewHas('users', fn ($users) => $users->whereIn('id', $targets->modelKeys())->isEmpty());
});
