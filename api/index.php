<?php
// Get URL from either path or query parameter
$url = '';
if (isset($_GET['url'])) {
    $url = urldecode($_GET['url']);
} else {
    $path = ltrim(parse_url($_SERVER['REQUEST_URI'], PATH_INFO) ?? '', '/');
    if (!empty($path)) {
        $url = (strpos($path, 'http') === 0) ? $path : 'https://' . $path;
    }
}

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid URL');
}

// Configure the request
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
$response = file_get_contents($url, false, $context);

// Forward content type from original response
foreach ($http_response_header as $header) {
    if (preg_match('/^content-type:/i', $header)) {
        header($header);
        break;
    }
}

// Process HLS playlists
if (strpos($header, 'application/vnd.apple.mpegurl') !== false || 
    strpos($header, 'application/x-mpegURL') !== false) {
    
    $proxyBase = 'https://'.$_SERVER['HTTP_HOST'].'/api/?url=';
    $lines = explode("\n", $response);
    
    foreach ($lines as &$line) {
        if (strpos($line, 'http') === 0) {
            $line = $proxyBase . urlencode($line);
        }
        // Handle relative paths
        elseif (!empty($line) && $line[0] !== '#' && strpos($line, '://') === false) {
            $absoluteUrl = dirname($url) . '/' . $line;
            $line = $proxyBase . urlencode($absoluteUrl);
        }
    }
    
    $response = implode("\n", $lines);
}

echo $response;
?>