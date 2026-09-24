<?php
require dirname(__DIR__) . '/schedule_http.php';
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
check(schedule_decode_response('{"events":[]}', 200, 'ESPN')['events'] === [], 'Empty season is valid');
foreach ([[403, '{"events":[]}'], [429, '{}'], [500, '{}'], [200, '<html>error</html>'], [200, 'null']] as [$status, $body]) {
    try {
        schedule_decode_response($body, $status, 'ESPN');
        throw new LogicException('Failure was accepted');
    } catch (RuntimeException $e) {
        check(str_contains($e->getMessage(), 'ESPN'), 'Failure needs source context');
    }
}

// Run the actual importer with fake API and DB boundaries in an isolated directory.
// Never include the application's production db_connect.php.
$fixture = sys_get_temp_dir() . '/fanfest-test-' . bin2hex(random_bytes(6));
mkdir($fixture, 0700);
copy(dirname(__DIR__) . '/import_games.php', $fixture . '/import_games.php');
file_put_contents($fixture . '/schedule_http.php', <<<'PHP'
<?php
function schedule_fetch($url, $label, $headers = []) {
    if (getenv('SCHEDULE_TEST_CASE') === 'failure') throw new RuntimeException("$label returned HTTP 403");
    if (getenv('SCHEDULE_TEST_CASE') === 'empty') return ['events' => []];
    return ['events' => [['name' => 'Away at Home', 'date' => '2026-09-23T23:00Z',
        'competitions' => [['venue' => ['fullName' => 'Example Park']]]]]];
}
PHP);
file_put_contents($fixture . '/db_connect.php', <<<'PHP'
<?php
class TestStatement {
    private array $params = [];
    public function __construct(private string $sql) {}
    public function bind_param($types, &...$params) { $this->params = &$params; }
    public function execute() {
        global $rows;
        if (str_starts_with($this->sql, 'INSERT')) {
            if (getenv('SCHEDULE_TEST_CASE') === 'dbfailure') throw new RuntimeException('Fixture DB write failure');
            $rows[$this->params[0] . '|' . substr($this->params[2], 0, 10)] = true;
        }
    }
    public function get_result() {
        global $rows;
        return (object)['num_rows' => isset($rows[$this->params[0] . '|' . substr($this->params[1], 0, 10)]) ? 1 : 0];
    }
}
$rows = [];
$conn = new class {
    public function prepare($sql) { return new TestStatement($sql); }
};
PHP);
try {
    putenv('FOOTBALL_DATA_API_TOKEN');
    foreach (['success', 'failure', 'empty', 'dbfailure'] as $case) {
        $marker = $fixture . '/last_game_import.txt';
        if (is_file($marker)) unlink($marker);
        putenv('SCHEDULE_TEST_CASE=' . $case);
        $lines = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture . '/import_games.php') . ' 2>&1', $lines, $exit);
        $text = implode("\n", $lines);
        if ($case === 'failure' || $case === 'dbfailure') {
            check($exit === 1 && !is_file($marker), "$case must fail without marking success");
            check(str_contains($text, 'Import incomplete'), 'Incomplete import must be visible');
        } else {
            check($exit === 0 && is_file($marker), "$case must record success");
            check(str_contains($text, 'New games inserted: ' . ($case === 'empty' ? '0' : '1')), 'Repeated event must not duplicate');
        }
    }
    echo "PASS: response validation, valid empty season, duplicates, HTTP failure, DB failure, and success markers.\n";
} finally {
    putenv('SCHEDULE_TEST_CASE');
    foreach (glob($fixture . '/*') as $file) unlink($file);
    rmdir($fixture);
}
