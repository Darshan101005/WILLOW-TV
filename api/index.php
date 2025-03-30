<?php
// Get URL from query parameter
$url = isset($_GET['url']) ? urldecode($_GET['url']) : '';

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid URL - Usage: /api?url=[encoded_stream_url]');
}

// Configure request
$options = [
    'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
            'Accept: */*',
            'Accept-Language: en-US,en;q=0.9'
        ]),
        'follow_location' => true,
        'ignore_errors' => true
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    header('HTTP/1.1 502 Bad Gateway');
    die('Failed to fetch stream');
}

// Forward content type
foreach ($http_response_header as $header) {
    if (preg_match('/^content-type:/i', $header)) {
        header($header);
        break;
    }
}

// Process HLS playlists
if (strpos($header, 'application/vnd.apple.mpegurl') !== false || 
    strpos($header, 'application/x-mpegURL') !== false) {
    
    $proxyBase = 'https://'.$_SERVER['HTTP_HOST'].'/api?url=';
    $lines = explode("\n", $response);
    
    foreach ($lines as &$line) {
        if (strpos($line, 'http') === 0) {
            $line = $proxyBase . urlencode($line);
        }
        elseif (!empty($line) && $line[0] !== '#' && strpos($line, '://') === false) {
            $absoluteUrl = dirname($url) . '/' . $line;
            $line = $proxyBase . urlencode($absoluteUrl);
        }
    }
    
    $response = implode("\n", $lines);
}

echo $response;
?>
