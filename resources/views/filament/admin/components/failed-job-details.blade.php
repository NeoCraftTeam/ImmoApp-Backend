@php
    $jobName = $payloadDecoded['displayName'] ?? 'Job inconnu';
    $attempts = $payloadDecoded['attempts'] ?? null;
    $maxTries = $payloadDecoded['maxTries'] ?? null;
    $timeout = $payloadDecoded['timeout'] ?? null;
    $jobId = $payloadDecoded['id'] ?? null;
    $exception = (string) $record->exception;

    // Redact sensitive keys from payload before display.
    $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'apiKey', 'credit_card', 'card_number', 'cvv', 'authorization', 'webhook_secret', 'stripe_secret', 'private_key'];
    $redactPayload = function (array $data) use (&$redactPayload, $sensitiveKeys): array {
        foreach ($data as $key => $value) {
            if (is_string($key) && collect($sensitiveKeys)->contains(fn ($s) => str_contains(strtolower($key), $s))) {
                $data[$key] = '██ REDACTED ██';
            } elseif (is_array($value)) {
                $data[$key] = $redactPayload($value);
            }
        }
        return $data;
    };
    $safePayload = $redactPayload($payloadDecoded);
@endphp

<div class="space-y-5">
    {{-- Meta panel --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">UUID</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-gray-100 break-all">{{ $record->uuid }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Échoué le</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $record->failed_at }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Connexion</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $record->connection }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">File</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $record->queue }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Job</dt>
                <dd class="mt-1 font-mono text-gray-900 dark:text-gray-100 break-all">{{ $jobName }}</dd>
            </div>
            @if ($attempts !== null)
                <div>
                    <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tentatives</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $attempts }}{{ $maxTries ? ' / '.$maxTries : '' }}</dd>
                </div>
            @endif
            @if ($timeout !== null)
                <div>
                    <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Timeout</dt>
                    <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $timeout }}s</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Exception trace --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Exception</h3>
        <pre class="rounded-xl border border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950/30 p-4 text-xs leading-relaxed text-red-900 dark:text-red-200 overflow-x-auto whitespace-pre-wrap break-all max-h-96">{{ $exception }}</pre>
    </div>

    {{-- Raw payload --}}
    <details class="rounded-xl border border-gray-200 dark:border-gray-700">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-900/40 rounded-xl">
            Payload complet (JSON)
        </summary>
        <pre class="px-4 pb-4 text-xs leading-relaxed text-gray-700 dark:text-gray-300 overflow-x-auto whitespace-pre-wrap break-all max-h-72">{{ json_encode($safePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </details>
</div>
