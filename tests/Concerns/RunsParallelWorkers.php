<?php

namespace Tests\Concerns;

use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

/**
 * Menjalankan beberapa proses PHP (tests/Support/*_worker.php) BERSAMAAN ke database testing.
 * Setiap worker menerima waktu mulai yang sama sebagai argumen terakhir, lalu menunggu sampai
 * waktu itu sebelum mengirim request, sehingga request benar-benar paralel.
 *
 * Proses lain hanya melihat data yang sudah di-commit: pakai DatabaseTruncation, bukan RefreshDatabase.
 */
trait RunsParallelWorkers
{
    /**
     * @param  list<list<string>>  $argsPerWorker  argumen tiap worker (tanpa waktu mulai)
     * @return Collection<int, array<string, mixed>>  output JSON tiap worker
     */
    protected function runParallelWorkers(string $script, array $argsPerWorker, array $env = []): Collection
    {
        $startAt = (string) (microtime(true) + 4);

        $env += [
            'APP_ENV'          => 'testing',
            'DB_CONNECTION'    => 'mysql',
            'DB_DATABASE'      => config('database.connections.mysql.database'),
            'CACHE_STORE'      => 'array',
            'SESSION_DRIVER'   => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL'      => 'null',
        ];

        $processes = collect($argsPerWorker)->map(function (array $args) use ($script, $startAt, $env) {
            $process = new Process(array_merge([PHP_BINARY, base_path("tests/Support/{$script}")], $args, [$startAt]), base_path(), $env, null, 60);
            $process->start();

            return $process;
        });

        return $processes->map(function (Process $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput() . $process->getOutput());

            return json_decode($process->getOutput(), true);
        });
    }

    protected function tearDownRunsParallelWorkers(): void
    {
        // Data yang di-commit untuk worker dibersihkan agar tidak terlihat oleh test lain
        $this->truncateTablesForAllConnections();
    }
}
