<?php

use App\Services\BsreClient;
use App\Services\BsreSigningProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('input.pdf', '%PDF-1.7 source');
});

function bsreProvider(array $overrides = []): BsreSigningProvider
{
    return new BsreSigningProvider(new BsreClient(array_merge([
        'base_url' => 'http://192.168.18.26',
        'sign_path' => '/api/sign/pdf',
        'download_path' => '/api/sign/download/{id}',
        'basic_username' => 'basic-user',
        'basic_password' => 'basic-secret',
        'bearer_token' => 'bearer-secret',
        'tampilan' => 'invisible',
        'image' => false,
        'timeout' => 5,
    ], $overrides)));
}

test('BSrE provider stores a PDF returned directly by the sign endpoint', function () {
    $requestShapeValid = false;
    Http::fake(function (Request $request) use (&$requestShapeValid) {
        $body = $request->body();
        $requestShapeValid = $request->url() === 'http://192.168.18.26/api/sign/pdf'
            && str_contains($body, 'name="nik"')
            && str_contains($body, '1234567890123456')
            && str_contains($body, 'name="passphrase"')
            && str_contains($body, 'do-not-log')
            && str_contains($body, 'name="tampilan"')
            && str_contains($body, 'invisible')
            && str_contains($body, 'name="image"')
            && str_contains($body, 'false')
            && $request->hasHeader('Authorization');

        return Http::response(
            '%PDF-1.7 signed',
            200,
            ['Content-Type' => 'application/pdf', 'id_dokumen' => 'bsre-123']
        );
    });

    $result = bsreProvider()->sign(
        'input.pdf',
        'output.pdf',
        'local-transaction',
        'Signer One',
        ['nik' => '1234567890123456', 'passphrase' => 'do-not-log']
    );

    Storage::disk('local')->assertExists('output.pdf');
    expect(Storage::disk('local')->get('output.pdf'))->toBe('%PDF-1.7 signed')
        ->and($result->providerTransactionId)->toBe('bsre-123')
        ->and($requestShapeValid)->toBeTrue();
});

test('BSrE provider downloads the signed PDF when sign returns a document id', function () {
    Http::fake(function (Request $request) {
        return $request->url() === 'http://192.168.18.26/api/sign/pdf'
            ? Http::response(['id_dokumen' => 'remote-456'], 200)
            : Http::response('%PDF-1.7 downloaded', 200, ['Content-Type' => 'application/pdf']);
    });

    $result = bsreProvider()->sign(
        'input.pdf',
        'output.pdf',
        'local-transaction',
        'Signer One',
        ['nik' => '1234567890123456', 'passphrase' => 'do-not-log']
    );

    expect(Storage::disk('local')->get('output.pdf'))->toBe('%PDF-1.7 downloaded')
        ->and($result->providerTransactionId)->toBe('remote-456');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://192.168.18.26/api/sign/download/remote-456');
});

test('BSrE provider fails clearly when credentials are not configured', function () {
    expect(fn () => bsreProvider([
        'basic_username' => null,
        'basic_password' => null,
        'bearer_token' => null,
    ])->sign('input.pdf', 'output.pdf', 'local-transaction', 'Signer One', [
        'nik' => '1234567890123456',
        'passphrase' => 'do-not-log',
    ]))->toThrow(ValidationException::class, 'Kredensial BSrE belum dikonfigurasi.');

    Http::assertNothingSent();
});
