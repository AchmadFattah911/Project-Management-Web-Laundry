@props([
    'action',
    'method' => 'POST',
    'confirm' => null,  // teks konfirmasi untuk aksi berisiko
    'token' => true,    // submission_token: submit ganda tetap 1 data
    'files' => false,
])

{{-- Form panel: CSRF, method spoofing, token idempotency, cegah klik ganda (loading), konfirmasi opsional --}}
<form
    action="{{ $action }}"
    method="POST"
    @if ($files) enctype="multipart/form-data" @endif
    x-data="{ sending: false }"
    x-on:submit="if (sending{{ $confirm ? ' || ! confirm(' . e(json_encode($confirm)) . ')' : '' }}) { $event.preventDefault(); return; } sending = true"
    {{ $attributes }}
>
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif
    @if ($token)
        <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}" />
    @endif

    {{ $slot }}
</form>
