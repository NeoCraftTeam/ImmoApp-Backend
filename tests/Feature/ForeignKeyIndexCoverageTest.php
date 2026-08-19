<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * Guard-rail: every foreign key column must be backed by an index whose
 * leading column is the FK itself. PostgreSQL never auto-indexes foreign
 * keys, so an un-indexed FK silently degrades JOINs and cascading deletes.
 *
 * This test fails the moment a new migration adds a foreign key without a
 * covering index — forcing the author to add one (see the
 * `add_missing_foreign_key_indexes_concurrent` migration for the pattern).
 */
it('has an index covering every foreign key column', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Foreign-key index audit is PostgreSQL-specific.');
    }

    $uncovered = DB::select(<<<'SQL'
        SELECT c.conrelid::regclass::text AS table_name, a.attname AS column_name
        FROM pg_constraint c
        JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
        WHERE c.contype = 'f'
          AND NOT EXISTS (
              SELECT 1 FROM pg_index i
              WHERE i.indrelid = c.conrelid
                AND a.attnum = i.indkey[0]
          )
        ORDER BY 1, 2
    SQL);

    $offenders = array_map(
        fn (object $row): string => "{$row->table_name}.{$row->column_name}",
        $uncovered
    );

    expect($offenders)->toBe(
        [],
        'Foreign keys missing a covering index: '.implode(', ', $offenders)
    );
});
