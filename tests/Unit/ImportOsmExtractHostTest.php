<?php

declare(strict_types=1);

use App\Console\Commands\ImportOsmExtract;

describe('resolveDatabaseHost', function (): void {
    it('resolves the host for the given connection shape', function (array $connection, string $expected): void {
        expect(ImportOsmExtract::resolveDatabaseHost($connection))->toBe($expected);
    })->with([
        'flat host key' => [['host' => 'db.internal'], 'db.internal'],
        'read/write split uses the write host' => [
            ['write' => ['host' => ['primary.db']], 'read' => ['host' => ['replica.db']], 'sticky' => true],
            'primary.db',
        ],
        'read-only split falls back to the read host' => [
            ['read' => ['host' => ['replica.db']]],
            'replica.db',
        ],
        'host provided as an array is unwrapped' => [
            ['host' => ['first.db', 'second.db']],
            'first.db',
        ],
        'DB_URL host is parsed when no host key exists' => [
            ['url' => 'pgsql://user:pass@url-host.db:5432/keyhome'],
            'url-host.db',
        ],
        'empty connection defaults to loopback' => [[], '127.0.0.1'],
        'blank host string defaults to loopback' => [['host' => ''], '127.0.0.1'],
        'flat host wins over the split hosts' => [
            ['host' => 'flat.db', 'write' => ['host' => ['primary.db']]],
            'flat.db',
        ],
    ]);
});
