<?php
// Can run from Railway cron/CLI or from the existing manual import page.
set_time_limit(0);
date_default_timezone_set('America/New_York');
ini_set('log_errors', '1');
require_once __DIR__ . '/schedule_http.php';

$lock = fopen(sys_get_temp_dir() . '/fanfest-import-' . sha1(__DIR__) . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo 'A schedule import is already running. Please refresh the check-in page shortly.';
    exit();
}
register_shutdown_function(function () use ($lock) {
    flock($lock, LOCK_UN);
    fclose($lock);
});

$import_errors = 0;
$fetched_events = 0;
function report_import_error(string $message): void {
    global $import_errors;
    $import_errors++;
    error_log('[FanFest schedule] ' . $message);
    echo '<p>Import error: ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
}
try {
require __DIR__ . '/db_connect.php';

echo "<h1>Importing League Schedules...</h1>\n";

// We MUST query ESPN day-by-day. If we ask for a 14-day range, their API returns empty for MLB!
$dates_to_fetch = [];
for ($i = 0; $i < 14; $i++) {
    $dates_to_fetch[] = date('Ymd', strtotime("+$i day"));
}

$leagues = [
    'MLB'   => ['sport' => 'baseball', 'slug' => 'mlb'],
    'NFL'   => ['sport' => 'football', 'slug' => 'nfl'],
    'NBA'   => ['sport' => 'basketball', 'slug' => 'nba'],
    'NCAAF' => ['sport' => 'football', 'slug' => 'college-football'],
    'NCAAB' => ['sport' => 'basketball', 'slug' => 'mens-college-basketball']
];

$inserted_count = 0;

// Prepare statements to check for duplicates (Prevents deleting fan data!)
$check_stmt = $conn->prepare("SELECT event_id FROM events WHERE event_name = ? AND DATE(event_date) = DATE(?)");
$insert_stmt = $conn->prepare("INSERT INTO events (event_name, location_name, event_date, league) VALUES (?, ?, ?, ?)");

// 1. Import ESPN Leagues Day-by-Day
foreach ($leagues as $league_name => $info) {
    echo "<p>Checking $league_name via ESPN...</p>";
    
    foreach ($dates_to_fetch as $date) {
        $api_url = "https://site.api.espn.com/apis/site/v2/sports/{$info['sport']}/{$info['slug']}/scoreboard?dates=$date";
        try {
            $data = schedule_fetch($api_url, "$league_name schedule for $date");
            if (!isset($data['events']) || !is_array($data['events'])) {
                throw new RuntimeException("$league_name response is missing its events array");
            }
        } catch (RuntimeException $e) {
            report_import_error($e->getMessage());
            break; // Do not send another 13 requests to a failing league endpoint.
        }
        {
            $fetched_events += count($data['events']);
            if (!empty($data['events'])) {
                foreach ($data['events'] as $event) {
                    $name = $event['name'];
                    
                    try {
                        $dateTime = new DateTime($event['date']);
                        $dateTime->setTimezone(new DateTimeZone('America/New_York'));
                        $game_date = $dateTime->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        $game_date = date('Y-m-d 12:00:00', time());
                    }
                    
                    $venue = $event['competitions'][0]['venue']['fullName'] ?? 'TBA';
                    
                    if (strpos($name, 'TBD') !== false || strpos($name, '/') !== false) continue;

                    // Check if game already exists
                    $check_stmt->bind_param("ss", $name, $game_date);
                    $check_stmt->execute();
                    
                    // Only insert if it doesn't exist
                    if ($check_stmt->get_result()->num_rows == 0) {
                        $insert_stmt->bind_param("ssss", $name, $venue, $game_date, $league_name);
                        $insert_stmt->execute();
                        $inserted_count++;
                    }
                }
            }
        }
    }
}

// 2. FIFA World Cup Import via football-data.org API
echo "<p>Checking FIFA World Cup matches via football-data.org...</p>";

$fifa_token = getenv('FOOTBALL_DATA_API_TOKEN');
$fifa_data = null;
if ($fifa_token) {
    try {
        $fifa_data = schedule_fetch(
            'https://api.football-data.org/v4/competitions/WC/matches',
            'FIFA schedule', ['X-Auth-Token: ' . $fifa_token]
        );
        if (!isset($fifa_data['matches']) || !is_array($fifa_data['matches'])) {
            throw new RuntimeException('FIFA response is missing its matches array');
        }
    } catch (RuntimeException $e) {
        report_import_error($e->getMessage());
    }
} else {
    echo '<p>FIFA skipped: FOOTBALL_DATA_API_TOKEN is not configured.</p>';
}

if ($fifa_data) {
    if (isset($fifa_data['matches'])) {
        foreach ($fifa_data['matches'] as $match) {
            $home = $match['homeTeam']['name'] ?? 'TBD';
            $away = $match['awayTeam']['name'] ?? 'TBD';
            $game_name = "$home vs $away";
            
            try {
                $dateTime = new DateTime($match['utcDate']);
                $dateTime->setTimezone(new DateTimeZone('America/New_York'));
                $game_date = $dateTime->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $game_date = date('Y-m-d H:i:s', strtotime($match['utcDate'])); 
            }
            
            $stadium = $match['venue']['name'] ?? '';
            $city = $match['venue']['location'] ?? '';
            $venue = (!empty($stadium) && !empty($city)) ? "$stadium, $city" : ($stadium ?: ($city ?: 'World Cup Stadium'));
            $league_name = 'FIFA';

            // Check if game already exists
            $check_stmt->bind_param("ss", $game_name, $game_date);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows == 0) {
                $insert_stmt->bind_param("ssss", $game_name, $venue, $game_date, $league_name);
                $insert_stmt->execute();
                $inserted_count++;
            }
        }
    }
}

} catch (Throwable $e) {
    error_log('[FanFest schedule] ' . $e->getMessage());
    report_import_error('The import stopped while connecting to or writing the database. Check the web service logs.');
}

if ($import_errors === 0) {
    if (file_put_contents(__DIR__ . '/last_game_import.txt', (string)time(), LOCK_EX) === false) {
        report_import_error('Could not save the successful-import timestamp');
    }
}
if ($import_errors > 0) {
    http_response_code(502);
    echo '<h3>Import incomplete</h3><p>The failed requests are recorded in the web service logs.</p>';
} else {
    echo '<h3>Import complete</h3>';
}
echo '<p>ESPN events received: ' . $fetched_events . '. New games inserted: ' . ($inserted_count ?? 0) . '.</p>';
echo "<a href='checkin.php'>Return to Dashboard</a>";
if (PHP_SAPI === 'cli' && $import_errors > 0) exit(1);
?>

