<?php
  // << 일기예보 응답 해석 >>
  // ** +900이상, -900 이하의 값은 Missing 값
  // ** SKY 하늘상태 (FCST 초단기예보 - 현시점부터 6시간 이내 예측)
  // - 1: clear    맑음
  // - 3: cloudy   구름많음
  // - 4: overcast 흐림
  // ** PTY 강수형태 (NCST 초단기실황 - 현시점의 관측값)
  // - 0: 없음 (FCST의 SKY를 참고해야 함)
  // - 1: rain               비
  // - 2: rain/snow          비/눈 -> 비 또는 눈 (sleet)으로 통일
  // - 3: snow               눈
  // - 4: shower             소나기 -> 비 (rain)으로 통일
  // - 5: drizzle            빗방울 -> 비 (rain)으로 통일
  // - 6: drizzle/snowflurry 빗방울/눈날림 -> 비 또는 눈 (sleet)으로 통일
  // - 7: snowflurry         눈날림 -> 눈 (snow)으로 통일
  // ** T1H 기온(°C)
  // ** RN1 1시간 강수량(mm)
  // ** REH 습도(%)
  // ** VEC 풍향(deg): (풍향값 + 22.5 * 0.5) / 22.5) = 변환값(소수점 이하 버림)
  // -  0: N   북
  // -  1: NNE 북북동
  // -  2: NE  북동
  // -  3: ENE 동북동
  // -  4: E   동
  // -  5: ESE 동남동
  // -  6: SE  남동
  // -  7: SSE 남남동
  // -  8: S   남
  // -  9: SSW 남남서
  // - 10: SW  남서
  // - 11: WSW 서남서
  // - 12: W   서
  // - 13: WNW 서북서
  // - 14: NW  북서
  // - 15: NNW 북북서
  // - 16: N   북
  // ** WSD 풍속(m/s)
  // << 대기오염정보 응답 해석 >>
  // ** pm25Value   초미세먼지 농도(㎍/㎥)
  // ** pm25Grade1h 초미세먼지 1시간 지수
  // ** pm25Flag    초미세먼지 측정장비 상태
  // ** pm10Value   미세먼지 농도(㎍/㎥)
  // ** pm10Grade1h 미세먼지 1시간 지수
  // ** pm10Flag    미세먼지 측정장비 상태
  // ** so2Value    아황산가스 농도(ppm)
  // ** so2Grade    아황산가스 지수
  // ** so2Flag     아황산가스 측정장비 상태
  // ** coValue     일산화탄소 농도(ppm)
  // ** coGrade     일산화탄소 지수
  // ** coFlag      일산화탄소 측정장비 상태
  // ** o3Value     오존 농도(ppm)
  // ** o3Grade     오존 지수
  // ** o3Flag      오존 측정장비 상태
  // ** no2Value    이산화질소 농도(ppm)
  // ** no2Grade    이산화질소 지수
  // ** no2Flag     이산화질소 측정장비 상태
  // ** Grade 값 해석
  // - 1: 좋음 (good)
  // - 2: 보통 (normal)
  // - 3: 나쁨
  // - 4: 매우나쁨
  //
  // PHP는 전역변수에 접근하려면 "global" 키워드 사용
  // 혹은 $GLOBALS["weatherDebug"]로 접근 가능
  global $weatherDebug;
  // 디버깅 출력 여부
  if ($weatherDebug) {
    echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - getWeatherInfo: DebugOutput enabled')</script>";
  }
  // DB 접속정보 및 API KEY
  require_once($_SERVER['DOCUMENT_ROOT']."/conf/secret.php");
  // 기상청 좌표 변환 함수
  require_once($_SERVER['DOCUMENT_ROOT']."/api/weather-dfs-xy-conv.php");
  
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
      return NULL;
    }
    // 기상청 좌표 계산
    $dfsLocation = NULL;
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
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60); // 연결 타임아웃
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
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60); // 연결 타임아웃
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
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60); // 연결 타임아웃
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
    $ncst = NULL;
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
      $ncst = (object) array(
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
        $ncst = (object) array(
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
    }
    // 강수형태가 0(없음)일 경우 초단기예보(FCST) 정보 가져오기
    $fcst = NULL;
    if ($ncst->PTY === "0") {
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
        $fcst = (object) array(
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
          $fcst = (object) array(
            "SKY" => "0"
          );
        } else {
          // 기존 날씨 정보를 유지 (아무 것도 하지 않음)
        }
        if ($weatherDebug) {
          echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] Failed to get weather-fcst data')</script>";
        }
      }
    } else {
      // ncst.PTY(강수형태)가 0(없음)이 아닌 경우
      $fcst = (object) array(
        "SKY" => "0"
      );
    }
    // 업데이트 시각 (신규 지역인 경우만)
    if ($isNewAddr) {
      $locationObj->updateTime = date("Y-m-d H:i:s");
    }
    // 측정소 이름 (대기오염정보 정보를 가져오기 위해 필요)
    if (empty($locationObj->station)) {
      // 측정소 이름이 없는 경우 주소 정보를 이용하여 측정소 이름을 찾는다
      // 1. 주소 정보로 TM 좌표계 알아내기
      $tmX = NULL;
      $tmY = NULL;
      $apiKey = $api_weather;
      $url = "http://apis.data.go.kr/B552584/MsrstnInfoInqireSvc/getTMStdrCrdnt";
      $url .= "?serviceKey=" . $apiKey;
      $url .= "&returnType=json&numOfRows=1&pageNo=1";
      $url .= "&umdName=" . urlencode($locationObj->address);
      // 에어코리아 서버로 요청하기
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
        echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] TM Coordinate: " . $response->response->header->resultMsg . "')</script>";
      }
      if (isset($response->response->body->items[0])) {
        // TM 좌표계 가져오기 성공
        $tmX = $response->response->body->items[0]->tmX;
        $tmY = $response->response->body->items[0]->tmY;
        // 2. TM 좌표계로 가까운 측정소 찾기
        $url = "http://apis.data.go.kr/B552584/MsrstnInfoInqireSvc/getNearbyMsrstnList";
        $url .= "?serviceKey=" . $apiKey;
        $url .= "&returnType=json&numOfRows=1&pageNo=1";
        $url .= "&tmX=" . $tmX . "&tmY=" . $tmY;
        // 에어코리아 서버로 요청하기
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
          echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] station: " . $response->response->header->resultMsg . "')</script>";
        }
        if (isset($response->response->body->items[0])) {
          // 측정소 이름 가져오기 성공
          $locationObj->station = $response->response->body->items[0]->stationName;
        } else {
          // 측정소 이름 가져오기 실패
          $locationObj->station = NULL;
          if ($weatherDebug) {
            echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] Failed to get station data')</script>";
          }
        }
      } else {
        // TM 좌표계 가져오기 실패
        $locationObj->station = NULL;
        if ($weatherDebug) {
          echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] Failed to get TM Coordinate data')</script>";
        }
      }
    }
    // 대기오염정보 가져오기
    $airPollutionInfo = NULL;
    if (empty($locationObj->station)) {
      // 측정소 이름이 없으면 대기오염정보를 가져올 수 없다.
      if ($weatherDebug) {
        echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] station: NULL')</script>";
        echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] Skip get Air Pollution Information data')</script>";
      }
    } else {
      // 측정소 이름 (디버깅 출력)
      if ($weatherDebug) {
        echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] station: " . $locationObj->station . "')</script>";
      }
      // 3. 측정소 이름으로 대기오염정보 가져오기
      $apiKey = $api_weather;
      $url = "http://apis.data.go.kr/B552584/ArpltnInforInqireSvc/getMsrstnAcctoRltmMesureDnsty";
      $url .= "?serviceKey=" . $apiKey;
      $url .= "&returnType=json&numOfRows=1&pageNo=1";
      $url .= "&stationName=" . urlencode($locationObj->station);
      $url .= "&dataTerm=daily&ver=1.4";
      // 에어코리아 서버로 요청하기
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
        echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] Air Pollution Information: " . $response->response->header->resultMsg . "')</script>";
      }
      if (isset($response->response->body)) {
        // 대기오염정보 가져오기 성공
        $airPollutionInfo = $response->response->body->items[0];
      } else {
        // 대기오염정보 가져오기 실패
        $airPollutionInfo = NULL;
        if ($weatherDebug) {
          echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [" . $locationObj->address . "] Failed to get Air Pollution Information data')</script>";
        }
      }
    }
    // 수집한 정보를 weather 객체에 맞추어 정리
    // 강수형태 및 하늘상태 (PTY, SKY)
    if ($ncst->PTY === "0") {
      switch ($fcst->SKY) {
        case "1":
          $locationObj->weather->sky = "clear";
          break;
        case "3":
          $locationObj->weather->sky = "cloudy";
          break;
        case "4":
          $locationObj->weather->sky = "overcast";
          break;
        default:
          $locationObj->weather->sky = "none";
          break;
      }
    } else {
      switch ($ncst->PTY) {
        case "1":
          $locationObj->weather->sky = "rain";
          break;
        case "2":
          // $locationObj->weather->sky = "rain/snow";
          $locationObj->weather->sky = "sleet";
          break;
        case "3":
          $locationObj->weather->sky = "snow";
          break;
        case "4":
          // $locationObj->weather->sky = "shower";
          $locationObj->weather->sky = "rain";
          break;
        case "5":
          // $locationObj->weather->sky = "drizzle";
          $locationObj->weather->sky = "rain";
          break;
        case "6":
          // $locationObj->weather->sky = "drizzle/snowflurry";
          $locationObj->weather->sky = "sleet";
          break;
        case "7":
          // $locationObj->weather->sky = "snowflurry";
          $locationObj->weather->sky = "snow";
          break;
        default:
          $locationObj->weather->sky = "none";
          break;
      }
    }
    // 기온 (T1H)
    if ((float) $ncst->T1H > -900 && (float) $ncst->T1H < 900) {
      $locationObj->weather->temperature = (float) $ncst->T1H;
    } else {
      $locationObj->weather->temperature = -1;
    }
    // 1시간 강수량 (RN1)
    if ((float) $ncst->RN1 > -900 && (float) $ncst->RN1 < 900) {
      $locationObj->weather->rainfall = (float) $ncst->RN1;
    } else {
      $locationObj->weather->rainfall = -1;
    }
    // 습도 (REH)
    if ((float) $ncst->REH > -900 && (float) $ncst->REH < 900) {
      $locationObj->weather->humidity = (float) $ncst->REH;
    } else {
      $locationObj->weather->humidity = -1;
    }
    // 풍향 (VEC)
    if ((float) $ncst->VEC > -900 && (float) $ncst->VEC < 900) {
      $vec = (float) $ncst->VEC;
      $calc = floor((($vec + (22.5 * 0.5)) / 22.5));
      switch ($calc) {
        case 0:
          $locationObj->weather->windDirection = "N";
          break;
        case 1:
          $locationObj->weather->windDirection = "NNE";
          break;
        case 2:
          $locationObj->weather->windDirection = "NE";
          break;
        case 3:
          $locationObj->weather->windDirection = "ENE";
          break;
        case 4:
          $locationObj->weather->windDirection = "E";
          break;
        case 5:
          $locationObj->weather->windDirection = "ESE";
          break;
        case 6:
          $locationObj->weather->windDirection = "SE";
          break;
        case 7:
          $locationObj->weather->windDirection = "SSE";
          break;
        case 8:
          $locationObj->weather->windDirection = "S";
          break;
        case 9:
          $locationObj->weather->windDirection = "SSW";
          break;
        case 10:
          $locationObj->weather->windDirection = "SW";
          break;
        case 11:
          $locationObj->weather->windDirection = "WSW";
          break;
        case 12:
          $locationObj->weather->windDirection = "W";
          break;
        case 13:
          $locationObj->weather->windDirection = "WNW";
          break;
        case 14:
          $locationObj->weather->windDirection = "NW";
          break;
        case 15:
          $locationObj->weather->windDirection = "NNW";
          break;
        case 16:
          $locationObj->weather->windDirection = "N";
          break;
        default:
          $locationObj->weather->windDirection = "none";
          break;
      }
    } else {
      $locationObj->weather->windDirection = "none";
    }
    // 풍속 (WSD)
    if ((float) $ncst->WSD > -900 && (float) $ncst->WSD < 900) {
      $locationObj->weather->windSpeed = (float) $ncst->WSD;
    } else {
      $locationObj->weather->windSpeed = -1;
    }
    if (empty($airPollutionInfo)) {
      // 미세먼지 데이터가 없는 경우 (관측소 이름이 없거나 대기오염정보를 가져오지 못한 경우)
      // 초미세먼지(pm2.5) 농도
      $locationObj->weather->pm25Value = -1;
      // 초미세먼지(pm2.5) 1시간 지수
      $locationObj->weather->pm25Grade = "none";
      // 미세먼지(pm10) 농도
      $locationObj->weather->pm10Value = -1;
      // 미세먼지(pm10) 1시간 지수
      $locationObj->weather->pm10Grade = "none";
    } else {
      // 초미세먼지(pm2.5) 농도
      $locationObj->weather->pm25Value = (int) $airPollutionInfo->pm25Value;
      // 초미세먼지(pm2.5) 1시간 지수
      switch ($airPollutionInfo->pm25Grade1h) {
        case "1":
          $locationObj->weather->pm25Grade = "good";
          break;
        case "2":
          $locationObj->weather->pm25Grade = "average";
          break;
        case "3":
          $locationObj->weather->pm25Grade = "bad";
          break;
        case "4":
          $locationObj->weather->pm25Grade = "verybad";
          break;
        default:
          $locationObj->weather->pm25Grade = "none";
          break;
      }
      // 미세먼지(pm10) 농도
      $locationObj->weather->pm10Value = (int) $airPollutionInfo->pm10Value;
      // 미세먼지(pm10) 1시간 지수
      switch ($airPollutionInfo->pm10Grade1h) {
        case "1":
          $locationObj->weather->pm10Grade = "good";
          break;
        case "2":
          $locationObj->weather->pm10Grade = "average";
          break;
        case "3":
          $locationObj->weather->pm10Grade = "bad";
          break;
        case "4":
          $locationObj->weather->pm10Grade = "verybad";
          break;
        default:
          $locationObj->weather->pm10Grade = "none";
          break;
      }
    }
    // 함수 정상 종료
    return true;
  }
?>