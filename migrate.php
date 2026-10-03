<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/inc/db.php';

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$files = array_values(array_filter(glob(__DIR__ . '/migrations/*.sql') ?: [], static fn(string $file): bool => preg_match('/^\d{3}_[^.]+\.sql$/', basename($file)) === 1));
sort($files, SORT_NATURAL);
foreach ($files as $file) {
    $version = basename($file, '.sql');
    $check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version=?');
    $check->execute([$version]);
    if ($check->fetchColumn()) {
        echo $version . ": already applied.\n";
        continue;
    }
    $sql = file_get_contents($file);
    try {
        foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', (string) $sql))) as $statement) {
            $pdo->exec($statement);
        }
        $insert = $pdo->prepare('INSERT INTO schema_migrations(version) VALUES(?)');
        $insert->execute([$version]);
        echo $version . ": applied.\n";
    } catch (Throwable $error) {
        fwrite(STDERR, $version . ': failed: ' . $error->getMessage() . "\n");
        exit(1);
    }
}
