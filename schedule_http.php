<?php
/** Download a schedule or explain why it failed; never confuse failure with an empty season. */
function schedule_decode_response(string $body, int $status, string $label): array {
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("$label returned HTTP $status");
    }
    try {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException("$label returned invalid JSON", 0, $e);
    }
    if (!is_array($data)) {
        throw new RuntimeException("$label returned an unexpected response");
    }
    return $data;
}

function schedule_fetch(string $url, string $label, array $headers = []): array {
    if (!extension_loaded('curl')) {
        throw new RuntimeException('The PHP cURL extension is required for schedule imports');
    }
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'curl/8.4.0',
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ]);
    $body = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($body === false) {
        throw new RuntimeException("$label could not be downloaded: $error");
    }
    return schedule_decode_response($body, $status, $label);
}
