<?php
  $request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

  switch ($request) {
    case '/' :
      http_response_code(403);
      require __DIR__ . '/index.html';
      break;
    case '/api/naver/search/local' :
      require __DIR__ . '/api/naver-search-local.php';
      break;
    case '/api/weather' :
      require __DIR__ . '/api/weather.php';
      break;
    case '/latlng' :
      require __DIR__ . '/page/latlng.php';
      break;
    case '/imgcoord' :
      require __DIR__ . '/page/imgcoord.html';
      break;
    default:
      http_response_code(403);
      require __DIR__ . '/index.html';
      break;
  }
?>