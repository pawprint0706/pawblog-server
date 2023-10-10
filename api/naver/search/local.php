<?php
  // CORS 허용
  header("Access-Control-Allow-Origin:*");
  // JSON 형식으로 응답
  header("Content-Type:application/json");
  // DB 접속정보 및 API KEY
  require_once($_SERVER['DOCUMENT_ROOT']."/conf/secret.php");
  // 라우팅
  if (isset($_GET["query"])) {
    // 쿼리를 전달 받은 경우
    $params = array(
      "query" => $_GET["query"],
      "display" => 5,
      "start" => 1,
      "sort" => "random"
    );
    $url = "https://openapi.naver.com/v1/search/local.json?" . http_build_query($params); // http_build_query는 URL인코딩된 결과물을 반환
    $headers = array(
      "Accept:*/*",
      "Content-Type:application/json",
      "Cache-Control:no-cache",
      "X-Naver-Client-Id:" . $api_naver_id,
      "X-Naver-Client-Secret:" . $api_naver_secret
    );
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // HTTP 버전 1.1 사용
    curl_setopt($curl, CURLOPT_URL, $url); // URL 지정
    curl_setopt($curl, CURLOPT_HEADER, false); // 응답 헤더를 표시할지 여부
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); // 헤더 지정
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // true인 경우 응답을 curl_exec()의 반환값으로 사용하며 false인 경우 바로 출력
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10); // 연결 타임아웃
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // HTTPS 인증 사용 여부 (자체 인증서를 사용하는 서버는 false로 설정)
    $response = curl_exec($curl);
    curl_close($curl);
    // 응답이 JSON 형식이므로 그대로 클라이언트로 응답
    echo $response; // CURLOPT_RETURNTRANSFER이 false인 경우에는 1이 출력됨
  } else {
    // 쿼리를 전달 받지 못한 경우
    $error = (object) array(
      "errorMessage" => "No query string (입력받은 쿼리가 없습니다)",
      "errorCode" => "000"
    );
    echo json_encode($error, JSON_UNESCAPED_UNICODE); // 한글 인코딩 깨짐 방지
  }
?>