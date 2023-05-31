<?php
  function dfs_xy_conv($code, $v1, $v2) {
    // LCC DFS 좌표변환
    // (toXY:위경도->좌표, v1:위도, v2:경도)
    // (toLL:좌표->위경도, v1:x, v2:y)
    //
    // (사용 예)
    // $rs = dfs_xy_conv("toLL","60","127");
    //
    // LCC DFS 좌표변환을 위한 기초 자료
    //
    $RE = 6371.00877; // 지구 반경(km)
    $GRID = 5.0; // 격자 간격(km)
    $SLAT1 = 30.0; // 투영 위도1(degree)
    $SLAT2 = 60.0; // 투영 위도2(degree)
    $OLON = 126.0; // 기준점 경도(degree)
    $OLAT = 38.0; // 기준점 위도(degree)
    $XO = 43; // 기준점 X좌표(GRID)
    $YO = 136; // 기준점 Y좌표(GRID)

    $DEGRAD = M_PI / 180.0;
    $RADDEG = 180.0 / M_PI;

    $re = $RE / $GRID;
    $slat1 = $SLAT1 * $DEGRAD;
    $slat2 = $SLAT2 * $DEGRAD;
    $olon = $OLON * $DEGRAD;
    $olat = $OLAT * $DEGRAD;

    $sn = tan(M_PI * 0.25 + $slat2 * 0.5) / tan(M_PI * 0.25 + $slat1 * 0.5);
    $sn = log(cos($slat1) / cos($slat2)) / log($sn);
    $sf = tan(M_PI * 0.25 + $slat1 * 0.5);
    $sf = pow($sf, $sn) * cos($slat1) / $sn;
    $ro = tan(M_PI * 0.25 + $olat * 0.5);
    $ro = $re * $sf / pow($ro, $sn);
    $rs = array();

    if ($code == "toXY") {
      $rs["lat"] = $v1;
      $rs["lng"] = $v2;
      $ra = tan(M_PI * 0.25 + ($v1) * $DEGRAD * 0.5);
      $ra = $re * $sf / pow($ra, $sn);
      $theta = $v2 * $DEGRAD - $olon;
      if ($theta > M_PI) $theta -= 2.0 * M_PI;
      if ($theta < -M_PI) $theta += 2.0 * M_PI;
      $theta *= $sn;
      $rs["x"] = floor($ra * sin($theta) + $XO + 0.5);
      $rs["y"] = floor($ro - $ra * cos($theta) + $YO + 0.5);
    } else {
      $rs["x"] = $v1;
      $rs["y"] = $v2;
      $xn = $v1 - $XO;
      $yn = $ro - $v2 + $YO;
      $ra = sqrt($xn * $xn + $yn * $yn);
      if ($sn < 0.0) $ra = -$ra;
      $alat = pow(($re * $sf / $ra), (1.0 / $sn));
      $alat = 2.0 * atan($alat) - M_PI * 0.5;

      if (abs($xn) <= 0.0) {
        $theta = 0.0;
      } else {
        if (abs($yn) <= 0.0) {
          $theta = M_PI * 0.5;
          if ($xn < 0.0) $theta = -$theta;
        } else {
          $theta = atan2($xn, $yn);
        }
      }
      $alon = $theta / $sn + $olon;
      $rs["lat"] = $alat * $RADDEG;
      $rs["lng"] = $alon * $RADDEG;
    }

    return $rs;
  }
?>