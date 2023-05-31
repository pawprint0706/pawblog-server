<?php
  // PHP는 전역변수에 접근하려면 "global" 키워드 사용
  // 혹은 $GLOBALS["weatherDebug"]로 접근 가능
  global $weatherDebug;
  // 디버깅 출력 여부
  if ($weatherDebug) {
    echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - getWeatherInfo: DebugOutput enabled')</script>";
  }
  // DB 접속정보 및 API KEY
  require_once("../conf/secret.php");
  // 기상청 좌표 변환 함수
  require_once("./weather-dfs-xy-conv.php");
  
  // 날씨 정보를 가져오는 함수
  function getWeatherInfo($isNewAddr, &$locationObj) {
    // 디버깅 출력 여부
    global $weatherDebug;
    // API Keys
    global $api_naver_cloud_id;
    global $api_naver_cloud_secret;
    global $api_weather;
    // 초단기실황(NCST), 초단기예보(FCST) 정보에서 원하는 카테고리의 값을 찾는 함수
    function findCategoryValue($items, $category, $value) {
      foreach ($items as $item) {
        if ($item->category === $category) {
          return $item->$value;
        }
      }
      return null;
    }
    // 기상청 좌표 계산
    $dfsLocation = null;
    if ($isNewAddr) {
      // 신규 지역일 경우 값 초기화
      $dfsLocation = (object) array("lat" => "0", "lng" => "0", "x" => "0", "y" => "0");
    } else {
      // 이미 저장된 지역일 경우 기존 값 사용
      $dfsLocation = (object) dfs_xy_conv("toXY", $locationObj->geocode->lat, $locationObj->geocode->lng);
    }
    if ($weatherDebug) {
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] dfsLocation: " . json_encode($dfsLocation) . "')</script>";
    }
    // 네이버 날씨 코드 (신규 지역일 때만 가져온다)
    if ($isNewAddr) {
      $url = "https://ac.weather.naver.com/ac?q_enc=utf-8&r_format=json&r_enc=utf-8&r_lt=1&st=1&q=" . urlencode($locationObj->address);
      $headers = array(
        "Accept:*/*",
        "Content-Type:application/json",
        "Cache-Control:no-cache"
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
      $response = json_decode($response);
      if (isset($response->items[0][0][1][0])) {
        $locationObj->navercode = $response->items[0][0][1][0];
      } else {
        // 네이버 날씨 코드 가져오기 실패
        $locationObj->navercode = "0";
      }
    }
    if ($weatherDebug) {
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] navercode: " . $locationObj->navercode . "')</script>";
    }
    // 네이버 지오코드 (기상청에 날씨 요청 시 사용하며 신규 지역일 때만 가져온다)
    if ($isNewAddr) {
      $url = "https://naveropenapi.apigw.ntruss.com/map-geocode/v2/geocode?query=" . urlencode($locationObj->address);
      $headers = array(
        "Accept:*/*",
        "Content-Type:application/json",
        "Cache-Control:no-cache",
        "X-NCP-APIGW-API-KEY-ID:" . $api_naver_cloud_id,
        "X-NCP-APIGW-API-KEY:" . $api_naver_cloud_secret
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
      $response = json_decode($response);
      if (isset($response->addresses[0]->x) && isset($response->addresses[0]->y)) {
        // 네이버 지오코드에서 x는 경도, y는 위도
        $locationObj->geocode->lat = $response->addresses[0]->y;
        $locationObj->geocode->lng = $response->addresses[0]->x;
        // 기상청 좌표로 변환
        $dfsLocation = (object) dfs_xy_conv("toXY", $locationObj->geocode->lat, $locationObj->geocode->lng);
      } else {
        // 네이버 지오코드 가져오기 실패
        $locationObj->geocode->lat = "0";
        $locationObj->geocode->lng = "0";
      }
    }
    if ($weatherDebug) {
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] geocode: " . json_encode($locationObj->geocode) . "')</script>";
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] dfsLocation: " . json_encode($dfsLocation) . "')</script>";
    }
    // 지오코드를 얻지 못하면 더이상 진행할 수 없다.
    if ($locationObj->geocode->lat === "0" && $locationObj->geocode->lng === "0") {
      if ($weatherDebug) {
        echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] Failed to get the geocode')</script>";
      }
      echo "{\"errorMessage\":\"Failed to get the geocode (지오코드를 가져오지 못했습니다)\"}";
      return false;
    }
    // 일출-일몰 시각 (sunrise-sunset.org API)
    $params = array(
      "lat" => $locationObj->geocode->lat,
      "lng" => $locationObj->geocode->lng,
      "date" => date("Y-m-d"),
      "formatted" => "0"
    );
    $url = "http://api.sunrise-sunset.org/json?" . http_build_query($params); // http_build_query는 URL인코딩된 결과물을 반환
    $headers = array(
      "Accept:*/*",
      "Content-Type:application/json",
      "Cache-Control:no-cache"
    );
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // HTTP 버전 1.1 사용
    curl_setopt($curl, CURLOPT_URL, $url); // URL 지정
    curl_setopt($curl, CURLOPT_HEADER, false); // 응답 헤더를 표시할지 여부
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); // 헤더 지정
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // true인 경우 응답을 curl_exec()의 반환값으로 사용하며 false인 경우 바로 출력
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30); // 연결 타임아웃
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // HTTPS 인증 사용 여부 (자체 인증서를 사용하는 서버는 false로 설정)
    $response = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($response);
    if ($response->status === "OK") {
      // $results->sunrise/sunset은 UTC 시간이며 strtotime() 함수에서 UNIX 타임스탬프로 변경되고
      // 이것을 date() 함수에서 Y-m-d H:i:s 형식으로 변환하는 과정에서 서버의 타임존이 적용된다.
      $locationObj->sunrise = date("Y-m-d H:i:s", strtotime($response->results->sunrise));
      $locationObj->sunset = date("Y-m-d H:i:s", strtotime($response->results->sunset));
    } else {
      // 일출-일몰 정보 가져오기 실패
      $locationObj->sunrise = date("Y-m-d") . " 06:00:00";
      $locationObj->sunset = date("Y-m-d") . " 18:00:00";
      if ($weatherDebug) {
        echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] Failed to get sunrise-sunset data')</script>";
      }
    }
    if ($weatherDebug) {
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] sunrise: " . $locationObj->sunrise . "')</script>";
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] sunset: " . $locationObj->sunset . "')</script>";
    }  
    // 기상청에서 초단기실황(NCST) 정보 가져오기
    // 초단기실황 정보 쿼리 만들기
    $now = new DateTime();
    $year = $now->format("Y");
    $month = $now->format("m");
    $day = $now->format("d");
    $hour = $now->format("H");
    $minute = $now->format("i");
    if ((int) $minute < 40) {
      // 40분보다 작으면 한시간 전 값
      $hour = (int) $hour - 1;
      if ((int) $hour < 0) {
        // 자정 이전은 전날로 계산
        $now->modify("-1 day");
        $year = $now->format("Y");
        $month = $now->format("m");
        $day = $now->format("d");
        $hour = "23";
        $minute = $now->format("i");
      }
      // 시간이 한자리인 경우 앞에 0을 덧붙임
      if ((int) $hour < 10) {
        $hour = "0" . $hour;
      }
    }
    $nx = $dfsLocation->x;
    $ny = $dfsLocation->y;
    $apiKey = $api_weather;
    $baseDate = $year . $month . $day;
    $baseTime = $hour . "00";
    $url = "http://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getUltraSrtNcst";
    $url .= "?serviceKey=" . $apiKey;
    $url .= "&pageNo=1&numOfRows=8&dataType=JSON";
    $url .= "&base_date=" . $baseDate;
    $url .= "&base_time=" . $baseTime;
    $url .= "&nx=" . $nx . "&ny=" . $ny;
    // 기상청 서버로 요청하기
    $headers = array(
      "Accept:*/*",
      "Content-Type:application/json",
      "Cache-Control:no-cache"
    );
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // HTTP 버전 1.1 사용
    curl_setopt($curl, CURLOPT_URL, $url); // URL 지정
    curl_setopt($curl, CURLOPT_HEADER, false); // 응답 헤더를 표시할지 여부
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); // 헤더 지정
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // true인 경우 응답을 curl_exec()의 반환값으로 사용하며 false인 경우 바로 출력
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60); // 연결 타임아웃
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // HTTPS 인증 사용 여부 (자체 인증서를 사용하는 서버는 false로 설정)
    $response = curl_exec($curl);
    curl_close($curl);
    $response = json_decode($response);
    if ($weatherDebug) {
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] ncst: " . $response->response->header->resultMsg . "')</script>";
    }
    if (isset($response->response->body)) {
      $locationObj->ncst = (object) array(
        "PTY" => findCategoryValue($response->response->body->items->item, "PTY", "obsrValue"),
        "REH" => findCategoryValue($response->response->body->items->item, "REH", "obsrValue"),
        "RN1" => findCategoryValue($response->response->body->items->item, "RN1", "obsrValue"),
        "T1H" => findCategoryValue($response->response->body->items->item, "T1H", "obsrValue"),
        "VEC" => findCategoryValue($response->response->body->items->item, "VEC", "obsrValue"),
        "WSD" => findCategoryValue($response->response->body->items->item, "WSD", "obsrValue")
      );
      if (!$isNewAddr) {
        // 이미 저장된 지역의 경우 업데이트 시각 갱신
        $locationObj->updateTime = date("Y-m-d H:i:s");
      }
    } else {
      // NCST 가져오기 실패
      if ($isNewAddr) {
        $locationObj->ncst = (object) array(
          "PTY" => "0",
          "REH" => "0",
          "RN1" => "0",
          "T1H" => "0",
          "VEC" => "0",
          "WSD" => "0"
        );
      } else {
        // 기존 날씨 정보를 유지 (아무 것도 하지 않음)  
      }
      if ($weatherDebug) {
        echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] Failed to get weather-ncst data')</script>";
      }
      echo "{\"errorMessage\":\"Failed to get weather-ncst data (초단기실황 정보를 가져오지 못했습니다)\"}";
    }
    // 강수형태가 0(없음)일 경우 초단기예보(FCST) 정보 가져오기
    if ($locationObj->ncst->PTY === "0") {
      // 기상청에서 초단기예보(FCST) 정보 가져오기
      // 초단기예보 정보 쿼리 만들기
      $now = new DateTime();
      $year = $now->format("Y");
      $month = $now->format("m");
      $day = $now->format("d");
      $hour = $now->format("H");
      $minute = $now->format("i");
      if ((int) $minute < 45) {
        // 45분보다 작으면 한시간 전 값
        $hour = (int) $hour - 1;
        if ((int) $hour < 0) {
          // 자정 이전은 전날로 계산
          $now->modify("-1 day");
          $year = $now->format("Y");
          $month = $now->format("m");
          $day = $now->format("d");
          $hour = "23";
          $minute = $now->format("i");
        }
        // 시간이 한자리인 경우 앞에 0을 덧붙임
        if ((int) $hour < 10) {
          $hour = "0" . $hour;
        }
      }
      $nx = $dfsLocation->x;
      $ny = $dfsLocation->y;
      $apiKey = $api_weather;
      $baseDate = $year . $month . $day;
      $baseTime = $hour . "30";
      $url = "http://apis.data.go.kr/1360000/VilageFcstInfoService_2.0/getUltraSrtFcst";
      $url .= "?serviceKey=" . $apiKey;
      $url .= "&pageNo=1&numOfRows=60&dataType=JSON";
      $url .= "&base_date=" . $baseDate;
      $url .= "&base_time=" . $baseTime;
      $url .= "&nx=" . $nx . "&ny=" . $ny;
      // 기상청 서버로 요청하기
      $headers = array(
        "Accept:*/*",
        "Content-Type:application/json",
        "Cache-Control:no-cache"
      );
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // HTTP 버전 1.1 사용
      curl_setopt($curl, CURLOPT_URL, $url); // URL 지정
      curl_setopt($curl, CURLOPT_HEADER, false); // 응답 헤더를 표시할지 여부
      curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); // 헤더 지정
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // true인 경우 응답을 curl_exec()의 반환값으로 사용하며 false인 경우 바로 출력
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60); // 연결 타임아웃
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // HTTPS 인증 사용 여부 (자체 인증서를 사용하는 서버는 false로 설정)
      $response = curl_exec($curl);
      curl_close($curl);
      $response = json_decode($response);
      if ($weatherDebug) {
        echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] fcst: " . $response->response->header->resultMsg . "')</script>";
      }
      if (isset($response->response->body)) {
        $locationObj->fcst = (object) array(
          // 각 카테고리 별로 시간 순으로 정렬 되어있다는 가정하에 첫번째 값을 가져온다
          "SKY" => findCategoryValue($response->response->body->items->item, "SKY", "fcstValue")
        );
        if (!$isNewAddr) {
          // 이미 저장된 지역의 경우 업데이트 시각 갱신
          $locationObj->updateTime = date("Y-m-d H:i:s");
        }
      } else {
        // FCST 가져오기 실패
        if ($isNewAddr) {
          $locationObj->fcst = (object) array(
            "SKY" => "0"
          );
        } else {
          // 기존 날씨 정보를 유지 (아무 것도 하지 않음)
        }
        if ($weatherDebug) {
          echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] Failed to get weather-fcst data')</script>";
        }
        echo "{\"errorMessage\":\"Failed to get weather-fcst data (초단기예보 정보를 가져오지 못했습니다)\"}";
      }
    } else {
      // ncst.PTY(강수형태)가 0(없음)이 아닌 경우
      $locationObj->fcst = (object) array(
        "SKY" => "0"
      );
    }
    // 업데이트 시각 (신규 지역인 경우만)
    if ($isNewAddr) {
      $locationObj->updateTime = date("Y-m-d H:i:s");
    }
    // 함수 정상 종료
    return true;
  }
?>