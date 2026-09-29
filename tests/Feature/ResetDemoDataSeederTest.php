<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;
use App\Services\SigningProvider;
use Database\Seeders\ResetDemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('reset preserves admin credentials and creates usable PDF workflows for ten users', function () {
    Storage::set('local', Storage::fake('reset-demo-'.Str::uuid()));
    config(['signwork.provider' => 'mock']);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $adminBefore = $admin->fresh()->getRawOriginal();
    $oldUser = User::factory()->create();
    $oldDocument = Document::factory()->create(['owner_id' => $oldUser->id]);
    $oldDocument->cycles()->create(['number' => 1, 'title' => 'Old', 'original_path' => 'signwork/old/source.pdf']);
    Storage::disk('local')->put('signwork/old/source.pdf', 'old source');
    Storage::disk('local')->put('unrelated.txt', 'keep');

    $this->seed(ResetDemoDataSeeder::class);

    expect($admin->fresh()->getRawOriginal())->toBe($adminBefore);
    $this->assertModelMissing($oldUser);
    $this->assertModelMissing($oldDocument);
    $this->assertDatabaseCount('users', 11);
    $this->assertDatabaseCount('documents', 200);
    $this->assertDatabaseCount('document_cycles', 210);
    $this->assertDatabaseCount('workflow_master_entries', 60);
    Storage::disk('local')->assertMissing('signwork/old/source.pdf');
    Storage::disk('local')->assertExists('unrelated.txt');
    foreach (User::where('role', UserRole::User->value)->get() as $user) {
        expect($user->nik)->toMatch('/^9997\\d{12}$/');
        expect($user->only(['jabatan', 'unit_kerja', 'pangkat', 'golongan']))
            ->each->not->toBeEmpty();
        expect($user->documents()->count())->toBe(20);
        expect(Hash::check('SignWorkDemo!2026', $user->password))->toBeTrue();
        foreach (['draft', 'waiting_approval', 'rejected', 'waiting_signature', 'signed'] as $status) {
            expect($user->documents()->where('status', $status)->count())->toBe(4);
        }
    }
    foreach (Document::all() as $document) {
        $cycle = $document->currentCycle();
        expect($document->requires_pdf_workflow)->toBeTrue();
        expect($cycle->status)->toBe($document->status->value);
        expect(hash_file('sha256', Storage::disk('local')->path($cycle->original_path)))->toBe($cycle->original_sha256);
        if ($document->status === DocumentStatus::Signed) {
            expect(hash_file('sha256', Storage::disk('local')->path($cycle->current_path)))->toBe($cycle->final_sha256);
            $steps = $cycle->signatures;
            expect($steps[0]->input_sha256)->toBe($cycle->prepared_sha256);
            expect($steps[1]->input_sha256)->toBe($steps[0]->output_sha256);
        }
    }
    $signed = Document::where('status', DocumentStatus::Signed)->firstOrFail();
    $this->actingAs($signed->owner)->get(route('documents.pdf.download', [$signed, 'version' => 'final']))->assertDownload();
    $cycle = $signed->currentCycle();
    $this->get(route('verification.show', $cycle->public_id))->assertSee('BUKAN TTE SAH');
    $revision = Document::where('revision_count', 1)->firstOrFail();
    expect($revision->cycles()->first()->status)->toBe('rejected');
    expect($revision->currentCycle()->approvals()->pluck('status')->all())->toBe(['pending', 'pending']);
});

test('worker preflight failure leaves existing users and documents intact', function () {
    Storage::set('local', Storage::fake('reset-demo-'.Str::uuid()));
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $document = Document::factory()->create();
    config(['signwork.python' => '/nonexistent/python']);

    expect(fn () => $this->seed(ResetDemoDataSeeder::class))->toThrow(ValidationException::class);

    $this->assertModelExists($admin);
    $this->assertModelExists($document);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('failure during mock signing rolls back deleted records and removes newly generated files', function () {
    Storage::set('local', Storage::fake('reset-demo-'.Str::uuid()));
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create();
    $document = Document::factory()->create(['owner_id' => $owner->id]);
    Storage::disk('local')->put('signwork/old.pdf', 'preserve');
    $document->cycles()->create(['number' => 1, 'title' => 'Old', 'original_path' => 'signwork/old.pdf']);
    $this->mock(SigningProvider::class)->shouldReceive('sign')->once()->andThrow(new RuntimeException('Simulated failure'));

    expect(fn () => $this->seed(ResetDemoDataSeeder::class))->toThrow(RuntimeException::class, 'Simulated failure');

    $this->assertModelExists($admin);
    $this->assertModelExists($owner);
    $this->assertModelExists($document);
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('documents', 1);
    expect(Storage::disk('local')->allFiles())->toBe(['signwork/old.pdf']);
});

test('reset refuses nonlocal environments before deleting anything', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->app->instance('env', 'production');

    expect(fn () => $this->seed(ResetDemoDataSeeder::class))->toThrow(LogicException::class);

    $this->assertModelExists($admin);
});

test('reset refuses an unsafe setup without changing records', function (string $scenario) {
    Storage::set('local', Storage::fake('reset-demo-'.Str::uuid()));
    $user = User::factory()->create();
    if ($scenario !== 'no_admin') {
        User::factory()->create(['role' => UserRole::Admin, 'email' => $scenario === 'email_collision' ? 'user01@demo.signwork.test' : 'admin@example.test']);
    }
    if ($scenario === 'real_provider') {
        config(['signwork.provider' => 'bsre']);
    }
    $before = User::count();

    expect(fn () => $this->seed(ResetDemoDataSeeder::class))->toThrow(LogicException::class);

    expect(User::count())->toBe($before);
    $this->assertModelExists($user);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['no_admin', 'email_collision', 'real_provider']);
