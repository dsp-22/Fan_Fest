<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db_connect.php';

header('Content-Type: application/json');

$user_lat = floatval($_GET['lat'] ?? 0);
$user_lon = floatval($_GET['lon'] ?? 0);

if ($user_lat === 0.0 || $user_lon === 0.0) {
    echo json_encode(['venues' => [], 'trending' => []]);
    exit();
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 3958.8; // Miles
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    return $earth_radius * $c;
}

// 1. Get Top 3 Closest Venues
$venues = [];

// Try database venues first (requires latitude/longitude columns in your events table)
$v_res = $conn->query("SELECT DISTINCT location_name, latitude, longitude FROM events WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
if ($v_res && $v_res->num_rows > 0) {
    while ($row = $v_res->fetch_assoc()) {
        $dist = calculateDistance($user_lat, $user_lon, (float)$row['latitude'], (float)$row['longitude']);
        $venues[] = [
            'name' => htmlspecialchars($row['location_name'] ?? 'TBA'),
            'distance' => $dist
        ];
    }
} else {
    // Graceful fallback to hardcoded list if your database lacks coords
    $fallback_venues = [
        ['name' => 'Franklin Hall (IN)', 'lat' => 39.1673, 'lon' => -86.5233],
        ['name' => 'Assembly Hall (IN)', 'lat' => 39.1809, 'lon' => -86.5222],
        ['name' => 'Memorial Stadium (IN)', 'lat' => 39.1809, 'lon' => -86.5256],
        ['name' => 'United Center (CHI)', 'lat' => 41.8806, 'lon' => -87.6742],
        ['name' => 'Soldier Field (CHI)', 'lat' => 41.8623, 'lon' => -87.6167],
        ['name' => 'Wrigley Field (CHI)', 'lat' => 41.9484, 'lon' => -87.6553],
        ['name' => 'Lincoln Financial Field (PHL)', 'lat' => 39.9008, 'lon' => -75.1675],
        ['name' => 'Citizens Bank Park (PHL)', 'lat' => 39.9061, 'lon' => -75.1665],
        ['name' => 'Rose Bowl (LA)', 'lat' => 34.1613, 'lon' => -118.1676]
    ];
    foreach ($fallback_venues as $v) {
        $venues[] = [
            'name' => $v['name'],
            'distance' => calculateDistance($user_lat, $user_lon, $v['lat'], $v['lon'])
        ];
    }
}

usort($venues, function($a, $b) { return $a['distance'] <=> $b['distance']; });
$top_venues = array_slice($venues, 0, 3);

// 2. Get Top 3 Trending Events (within 200 miles)
$trending = [];
$t_res = $conn->query("
    SELECT e.event_id, e.event_name, e.location_name, e.league, e.latitude, e.longitude, COUNT(c.checkin_id) as total_attendance
    FROM checkins c
    JOIN events e ON c.event_id = e.event_id
    GROUP BY e.event_id
    HAVING total_attendance > 0
    ORDER BY total_attendance DESC
");

if ($t_res) {
    while ($row = $t_res->fetch_assoc()) {
        // If the database has coordinates for the event, only show it if it's within 200 miles
        if (!empty($row['latitude']) && !empty($row['longitude'])) {
            $event_dist = calculateDistance($user_lat, $user_lon, (float)$row['latitude'], (float)$row['longitude']);
            if ($event_dist > 200.0) {
                continue; // Skip events far away
            }
        }
        
        $trending[] = [
            'event_name' => htmlspecialchars($row['event_name'] ?? 'TBA'),
            'location_name' => htmlspecialchars($row['location_name'] ?? 'TBA'),
            'league' => htmlspecialchars($row['league'] ?? ''),
            'total_attendance' => (int)$row['total_attendance']
        ];
        
        if (count($trending) >= 3) break; // Only take top 3 local ones
    }
}

echo json_encode([
    'venues' => $top_venues,
    'trending' => $trending
]);
?>
