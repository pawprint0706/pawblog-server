<?php
  // 기상 코드 값 해석
  // +900이상, -900 이하의 값은 Missing 값
  //
  // ** SKY 하늘상태 (FCST 초단기예보 - 현시점부터 6시간 이내 예측)
  // - 1: clear    맑음
  // - 3: cloudy   구름많음
  // - 4: overcast 흐림
  //
  // ** PTY 강수형태 (NCST 초단기실황 - 현시점의 관측값)
  // - 0: 없음 (FCST의 SKY를 참고해야 함)
  // - 1: rain               비
  // - 2: rain/snow          비/눈
  // - 3: snow               눈
  // - 4: shower             소나기
  // - 5: drizzle            빗방울
  // - 6: drizzle/snowflurry 빗방울/눈날림
  // - 7: snowflurry         눈날림
  //
  // ** T1H 기온(°C)
  //
  // ** RN1 1시간 강수량(mm)
  //
  // ** REH 습도(%)
  //
  // ** VEC 풍향(deg)
  // - (풍향값 + 22.5 * 0.5) / 22.5) = 변환값(소수점 이하 버림)
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
  //
  // ** WSD 풍속(m/s)
  //
  // 디버깅 출력 여부
  $weatherDebug = false;
  if ($weatherDebug) {
    echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - Weather API: DebugOutput enabled')</script>";
  } else {
    // JSON 형식으로 응답
    header("Content-Type:application/json");
  }
  // CORS 허용
  header("Access-Control-Allow-Origin:*");
  // DB 접속정보 및 API KEY
  require_once("../comm.php");
  // 날씨 정보를 가져오는 함수
  require_once("./weather-get-weather-info.php");

  // DB 연결하기
  $dbConn = new mysqli($host, $user, $pass, $db, $port);
  if (!$dbConn) {
    if ($weatherDebug) {
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - DB connection failed')</script>";
    }
    $error = (object) array("errorMessage" => "DB connection failed (DB 접속에 실패하였습니다)");
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
    exit();
  }
  // 인코딩 관련
  // $dbConn->query("SET NAMES euckr");
  $dbConn->query("SET NAMES utf8");

  // 라우팅
  // 주소로 날씨 가져오기
  if (isset($_GET["address"])) {
    // DB에 해당 주소로 기존에 가져온 정보가 있는지 체크
    $result = $dbConn->query("SELECT * FROM weather_cache_tbl WHERE address='" . $_GET["address"] . "'");
    if ($result->num_rows > 0) {
      // 해당 주소로 질의 받은 적이 있는 경우
      $row = $result->fetch_assoc(); // SELECT한 결과의 첫번째 행을 가져옴 (연관배열)
      // 10분 이내의 데이터라면 그대로 반환한다
      $currentDateTime = new DateTime(); // 현재 시간
      $updateDateTime = new DateTime($row["updateTime"]); // 업데이트 시간
      $interval = $currentDateTime->diff($updateDateTime); // 두 시간 사이의 차이 계산
      $minutes = $interval->i + ($interval->h * 60) + ($interval->d * 24 * 60); // 분 단위로 변환
      if ($minutes < 10) {
        if ($weatherDebug) {
          echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [EXIST] " . $_GET["address"] . "')</script>";
        }
        // 기존 JSON 문자열 파싱
        $row["geocode"] = json_decode($row["geocode"]);
        $row["ncst"] = json_decode($row["ncst"]);
        $row["fcst"] = json_decode($row["fcst"]);
        $row["weather"] = json_decode($row["weather"]);
        // 클라이언트에 응답
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
      } else {
        // 10분 이상 과거의 데이터이므로 최신 일출/일몰/초단기실황/초단기예보 정보를 가져온다
        if ($weatherDebug) {
          echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [UPDATE] " . $_GET["address"] . "')</script>";
        }
        // 기존 JSON 문자열 파싱
        $row["geocode"] = json_decode($row["geocode"]);
        $row["ncst"] = json_decode($row["ncst"]);
        $row["fcst"] = json_decode($row["fcst"]);
        $row["weather"] = json_decode($row["weather"]);
        // 일출/일몰/초단기실황/초단기예보 업데이트
      }
    } else {
      // 처음 질의 받은 지역명일 경우 DB에 새로운 레코드를 생성한다
      if ($weatherDebug) {
        echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [NEW] " . $_GET["address"] . "')</script>";
      }
      // 새로운 지역 객체 생성
      class Location {
        public $address;
        public $navercode;
        public $geocode;
        public $sunrise;
        public $sunset;
        public $ncst;
        public $fcst;
        public $weather;
        public $updateTime;
      }
      $location = new Location();
      $location->address = $_GET["address"];
      $location->navercode = "0";
      $location->geocode = (object) array("lat" => "0", "lng" => "0");
      $location->sunrise = "2022-01-01 06:00:00";
      $location->sunset = "2022-01-01 18:00:00";
      $location->ncst = (object) array("PTY" => "0", "T1H" => "0", "RN1" => "0", "REH" => "0", "VEC" => "0", "WSD" => "0");
      $location->fcst = (object) array("SKY" => "0");
      $location->weather = (object) array("data" => "null");
      $location->updateTime = "2022-01-01 09:00:00";
      // 일출/일몰/초단기실황/초단기예보 업데이트
    }
  } else {
    // 주소를 전달 받지 못한 경우
    if ($weatherDebug) {
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - No address input')</script>";
    }
    $error = (object) array("errorMessage" => "No address input (입력받은 주소가 없습니다)");
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
    exit();
  }
?>