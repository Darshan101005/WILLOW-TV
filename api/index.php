<?php
// Get URL from either PATH_INFO or query parameter
if (isset($_SERVER['PATH_INFO']) && strlen($_SERVER['PATH_INFO']) > 1) {
    $url = ltrim($_SERVER['PATH_INFO'], '/');
    // Add protocol if missing
    if (strpos($url, 'http') !== 0) {
        $url = 'https://' . $url;
    }
} elseif (isset($_GET['url'])) {
    $url = urldecode($_GET['url']);
} else {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid URL - Use /api/http://example.com/stream.m3u8 or /api?url=http://example.com/stream.m3u8');
}

// Validate URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    header('HTTP/1.1 400 Bad Request');
    die('Malformed URL');
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

// Fetch content
$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    header('HTTP/1.1 502 Bad Gateway');
    die('Upstream request failed');
}

// Forward content-type
foreach ($http_response_header as $header) {
    if (preg_match('/^content-type:/i', $header)) {
        header($header);
        break;
    }
}

// Process HLS playlists
if (strpos($header, 'application/vnd.apple.mpegurl') !== false || 
    strpos($header, 'application/x-mpegURL') !== false) {
    
    $proxyBase = 'https://'.$_SERVER['HTTP_HOST'].'/api/';
    $lines = explode("\n", $response);
    
    foreach ($lines as &$line) {
        if (strpos($line, 'http') === 0) {
            $line = $proxyBase . $line;
        }
        elseif (!empty($line) && $line[0] !== '#' && strpos($line, '://') === false) {
            $absoluteUrl = dirname($url) . '/' . $line;
            $line = $proxyBase . $absoluteUrl;
        }
    }
    
    $response = implode("\n", $lines);
}

echo $response;
?>
