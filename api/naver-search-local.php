<?php
  if (isset($_GET["query"])) {
    // 쿼리를 전달 받은 경우
    $params = array(
      "query" => $_GET["query"],
      "display" => 5,
      "start" => 1,
      "sort" => "random"
    );
    $url = "https://openapi.naver.com/v1/search/local.json?" . http_build_query($params);
    $headers = array(
      "Accept:*/*",
      "Content-Type:application/json",
      "Cache-Control:no-cache",
      "X-Naver-Client-Id:{YourClientID}",
      "X-Naver-Client-Secret:{YourClientSecret}"
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
    echo $response; // CURLOPT_RETURNTRANSFER이 false인 경우에는 1이 출력됨
  } else {
    // 쿼리를 전달 받지 못한 경우
    echo "{\"errorMessage\":\"No query string (입력받은 쿼리가 없습니다)\",\"errorCode\":\"000\"}";
  }
?>