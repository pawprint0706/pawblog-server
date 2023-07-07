<?php
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
  require_once($_SERVER['DOCUMENT_ROOT']."/conf/secret.php");
  // 날씨 정보를 가져오는 함수
  require_once($_SERVER['DOCUMENT_ROOT']."/api/weather-get-weather-info.php");

  // DB 연결하기
  $dbConn = new mysqli($host, $user, $pass, $db, $port);
  if (!$dbConn) {
    if ($weatherDebug) {
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - DB connect failed')</script>";
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
        $row["weather"] = json_decode($row["weather"]);
        // 클라이언트에 응답
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
      } else {
        // 10분 이상 과거의 데이터이므로 최신 일출/일몰/초단기실황/초단기예보 정보를 가져온다
        if ($weatherDebug) {
          echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [UPDATE] " . $_GET["address"] . "')</script>";
        }
        // 기존 JSON 문자열 파싱
        // $row는 연관배열이므로 이를 객체로 변환한다
        class Location {
          public $address;
          public $navercode;
          public $geocode;
          public $sunrise;
          public $sunset;
          public $station;
          public $weather;
          public $updateTime;
        }
        $location = new Location();
        $location->address = $row["address"];
        $location->navercode = $row["navercode"];
        $location->geocode = json_decode($row["geocode"]);
        $location->sunrise = $row["sunrise"];
        $location->sunset = $row["sunset"];
        $location->station = $row["station"];
        $location->weather = json_decode($row["weather"]);
        $location->updateTime = $row["updateTime"];
        // 일출/일몰/초단기실황/초단기예보 업데이트
        $weatherResult = getWeatherInfo(false, $location);
        if ($weatherResult) {
          // 새 날씨 정보를 DB에 갱신하기
          $queryResult = $dbConn->query("UPDATE weather_cache_tbl SET address='" . $location->address . "', navercode='" . $location->navercode . "', geocode='" . json_encode($location->geocode) . "', sunrise='" . $location->sunrise . "', sunset='" . $location->sunset . "', station='" . $location->station . "', weather='" . json_encode($location->weather) . "', updateTime='" . $location->updateTime . "' WHERE address='" . $location->address . "'");
          if (!$queryResult) {
            if ($weatherDebug) {
              echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - DB update failed (" . $_GET["address"] . ")')</script>";
            }
          }
          // 클라이언트에 응답
          echo json_encode($location, JSON_UNESCAPED_UNICODE);
        } else {
          if ($weatherDebug) {
            echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [FAIL] " . $_GET["address"] . "')</script>";
          }
          $error = (object) array("errorMessage" => "Failed to get weather data (날씨 정보를 가져오지 못했습니다)");
          echo json_encode($error, JSON_UNESCAPED_UNICODE);
        }
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
        public $station;
        public $weather;
        public $updateTime;
      }
      $location = new Location();
      $location->address = $_GET["address"];
      $location->navercode = "0";
      $location->geocode = (object) array("lat" => "0", "lng" => "0");
      $location->sunrise = "2022-01-01 06:00:00";
      $location->sunset = "2022-01-01 18:00:00";
      $location->station = NULL;
      $location->weather = (object) array(
        "sky" => NULL,
        "temperature" => 0,
        "rainfall" => 0,
        "humidity" => 0,
        "windDirection" => NULL,
        "windSpeed" => 0,
        "pm25Value" => 0,
        "pm25Grade" => NULL,
        "pm10Value" => 0,
        "pm10Grade" => NULL
      );
      $location->updateTime = "2022-01-01 09:00:00";
      // 일출/일몰/초단기실황/초단기예보 업데이트
      $weatherResult = getWeatherInfo(true, $location);
      if ($weatherResult) {
        // 새 주소의 날씨 정보를 DB에 갱신하기
        $queryResult = $dbConn->query("INSERT INTO weather_cache_tbl VALUES ('" . $location->address . "', '" . $location->navercode . "', '" . json_encode($location->geocode) . "', '" . $location->sunrise . "', '" . $location->sunset . "', '" . $location->station . "', '" . json_encode($location->weather) . "', '" . $location->updateTime . "')");
        if (!$queryResult) {
          if ($weatherDebug) {
            echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - DB insert failed (" . $_GET["address"] . ")')</script>";
          }
        }
        // 클라이언트에 응답
        echo json_encode($location, JSON_UNESCAPED_UNICODE);
      } else {
        if ($weatherDebug) {
          echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - [FAIL] " . $_GET["address"] . "')</script>";
        }
        $error = (object) array("errorMessage" => "Failed to get weather data (날씨 정보를 가져오지 못했습니다)");
        echo json_encode($error, JSON_UNESCAPED_UNICODE);
      }
    }
  } else {
    // 주소를 전달 받지 못한 경우
    if ($weatherDebug) {
      echo "<script>console.log('" . date("Y-m-d H:i:s T") . " - No address input')</script>";
    }
    $error = (object) array("errorMessage" => "No address input (입력받은 주소가 없습니다)");
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
  }
?>