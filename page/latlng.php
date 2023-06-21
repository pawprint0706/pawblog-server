<?php
  // DB 접속정보 및 API KEY
  require_once("../conf/secret.php");
?>
<!DOCTYPE html>
<html lang="ko">
  <head>
    <meta charset="utf-8">
    <title>위도 경도 주소 확인하기</title>
    <!-- Axios -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <!-- 네이버 지도 API -->
    <script type="text/javascript" src="https://openapi.map.naver.com/openapi/v3/maps.js?ncpClientId=<?= $api_naver_cloud_id ?>&submodules=geocoder"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- 스타일 -->
    <style>
      body {
        margin: 0;
        padding: 0;
        border: hidden;
        overflow: hidden;
      }
      div#map {
        margin: 0;
        padding: 0;
        border: hidden;
        overflow: hidden;
        width: 100vw;
        height: 100vh;
      }
      div.infoWnd {
        margin: 0;
        padding: 10px;
        width: 400px;
        height: auto;
        text-align: left;
        line-height: 1.5em;
        cursor: text;
      }
      div.infoWnd-coord {
        display: grid;
        grid-template-rows: max-content max-content;
        place-content: stretch;
        margin: 0;
        padding: 10px;
        width: 200px;
        height: auto;
      }
      div.infoWnd-coord.text {
        margin: 0;
        padding: 0;
        border: 0;
        text-align: left;
        line-height: 1.5em;
        cursor: text;
      }
      div.infoWnd-coord.button {
        display: grid;
        grid-template-columns: 1fr 1fr;
        column-gap: 5px;
        place-content: stretch;
        margin: 0;
        padding: 0;
        border: 0;
        cursor: pointer;
      }
      button.copy-button {
        margin: 5px 0px 0px 0px;
        padding: 5px 0px;
        border: 1px solid black;
        outline: none;
        color: white;
        text-align: center;
        background-color: #2f87ec;
        cursor: pointer;
        -ms-user-select: none; 
        -moz-user-select: -moz-none;
        -khtml-user-select: none;
        -webkit-user-select: none;
        user-select: none;
      }
      button.copy-button:active {
        color: black;
        background-color: #a0ccff;
      }
      div.searchbox {
        position: absolute;
        display: grid;
        grid-template-columns: max-content max-content max-content;
        top: 10px;
        left: 10px;
        margin: 0;
        padding: 0;
        border: 4px solid #19CE60;
        background-color: white;
        z-index: 10000;
      }
      input#searchbox-input {
        width: 300px;
        height: 40px;
        margin: 0;
        padding: 0px 10px;
        border: 0;
        border-right: 0;
        outline: none;
        font-size: 16px;
        font-weight: bold;
      }
      input#searchbox-input::placeholder {
        color: #B9B9B9;
      }
      button#searchbox-clear-button {
        visibility: hidden;
        display: grid;
        place-items: center;
        width: 20px;
        height: 40px;
        margin: 0;
        margin-right: 10px;
        padding: 0;
        border: 0;
        outline: none;
        background-color: white;
        cursor: pointer;
        -ms-user-select: none; 
        -moz-user-select: -moz-none;
        -khtml-user-select: none;
        -webkit-user-select: none;
        user-select: none;
      }
      button#searchbox-clear-button span {
        font-size: 20px;
        color: #B9B9B9;
      }
      button#searchbox-button {
        display: grid;
        place-items: center;
        width: 40px;
        height: 40px;
        margin: 0;
        padding: 0;
        border: 0;
        outline: none;
        background-color: #19CE60;
        cursor: pointer;
        -ms-user-select: none; 
        -moz-user-select: -moz-none;
        -khtml-user-select: none;
        -webkit-user-select: none;
        user-select: none;
      }
      button#searchbox-button span {
        font-size: 28px;
        color: white;
      }
      div.result {
        display: none;
        grid-template-columns: max-content max-content;
        place-content: stretch;
        position: absolute;
        top: 58px;
        left: 10px;
        margin: 0;
        padding: 0;
        border: 0;
        z-index: 10000;
      }
      button#result-close-button {
        display: grid;
        place-items: center;
        width: 24px;
        height: 24px;
        margin: 0;
        padding: 0;
        border: 1px solid black;
        border-left: 0;
        outline: none;
        background-color: white;
        cursor: pointer;
        -ms-user-select: none; 
        -moz-user-select: -moz-none;
        -khtml-user-select: none;
        -webkit-user-select: none;
        user-select: none;
      }
      button#result-close-button span {
        font-size: 20px;
        color: black;
      }
      table.result {
        margin: 0;
        padding: 0;
        border: 0;
        border-collapse: collapse;
        border-spacing: 0;
        background-color: white;
      }
      table.result thead tr th {
        margin: 0;
        padding: 5px 10px;
        border: 1px solid black;
        font-size: 16px;
        font-weight: bold;
        color: black;
      }
      table.result tbody tr td {
        margin: 0;
        padding: 5px 10px;
        border: 1px solid black;
        font-size: 16px;
        color: black;
        cursor: pointer;
      }
    </style>
  </head>
  <body>
    <div id="map"></div>
    <div class="searchbox">
      <input type="text" id="searchbox-input" placeholder="건물 이름 및 주소 검색">
      <button type="button" id="searchbox-clear-button">
        <span class="material-icons">close</span>
      </button>
      <button type="button" id="searchbox-button">
        <span class="material-icons">search</span>
      </button>
    </div>
    <div class="result">
      <table class="result">
        <thead>
          <tr>
            <th>이름</th>
            <th>주소</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <button type="button" id="result-close-button">
        <span class="material-icons">close</span>
      </button>
    </div>
    <script>
      // 네이버 지역 검색 (건물 이름 및 주소로 위치 찾기)
      function naverSearchLocal(query) {
        const params = new URLSearchParams();
        params.append('query', query);
        return axios.get('/api/naver-search-local.php', {
          headers: {
            'Accept':'*/*',
            'Content-Type':'application/json',
            'Cache-Control':'no-cache'
          },
          params: params
        })
      }
      // 네이버 지오코딩 (주소로 좌표 찾기)
      function searchAddressToCoordinate(_address) {
        return new Promise((resolve, reject) => {
          naver.maps.Service.geocode({
            query: _address
          }, (status, response) => {
            if (status === naver.maps.Service.Status.ERROR) {
              reject({ error:'API 서비스 상태 에러' });
            } else if (response.v2.meta.totalCount === 0) {
              reject({ error:'검색 결과가 없습니다' });
            } else {
              resolve(response.v2.addresses);
            }
          });
        });
      }
      // 네이버 리버스 지오코딩 (좌표로 주소 찾기)
      function hasArea(area) {
        return !!(area && area.name && area.name !== '');
      }
      function hasData(data) {
        return !!(data && data !== '');
      }
      function checkLastString (word, lastString) {
        return new RegExp(lastString + '$').test(word);
      }
      function hasAddition (addition) {
        return !!(addition && addition.value);
      }
      function makeAddress(item) {
        if (!item) {
          return;
        }
        let sido = '', sigugun = '', dongmyun = '', ri = '', rest = '';
        if (hasArea(item.region.area1)) {
          sido = item.region.area1.name;
        }
        if (hasArea(item.region.area2)) {
          sigugun = item.region.area2.name;
        }
        if (hasArea(item.region.area3)) {
          dongmyun = item.region.area3.name;
        }
        if (hasArea(item.region.area4)) {
            ri = item.region.area4.name;
        }
        if (item.land) {
          if (hasData(item.land.number1)) {
            if (hasData(item.land.type) && item.land.type === '2') {
              rest += '산';
            }
            rest += item.land.number1;
            if (hasData(item.land.number2)) {
              rest += ('-' + item.land.number2);
            }
          }
          if (item.name === 'roadaddr') {
            if (checkLastString(dongmyun, '면')) {
              ri = item.land.name;
            } else {
              dongmyun = item.land.name;
              ri = '';
            }
            if (hasAddition(item.land.addition0)) {
              rest += ' ' + item.land.addition0.value;
            }
          }
        }
        let addr = [sido, sigugun, dongmyun, ri, rest].join(' ');
        sido = sigugun = dongmyun = ri = rest = null; // 메모리 정리
        return addr;
      }
      function searchCoordinateToAddress(_lat, _lng) {
        return new Promise((resolve, reject) => {
          naver.maps.Service.reverseGeocode({
            coords: new naver.maps.LatLng(_lat, _lng),
            orders: [naver.maps.Service.OrderType.ADDR].join(',')
          }, function(status, response) {
            if (status === naver.maps.Service.Status.ERROR) {
              reject({ error:'API 서비스 상태 에러' });
            } else {
              if (response.v2.results.length === 0) {
                reject({ error:'검색 결과가 없습니다' });
              } else {
                let addresses = [];
                for (let i = 0; i < response.v2.results.length; i++) {
                  let address = makeAddress(response.v2.results[i]) || '';
                  addresses.push(address);
                  address = null; // 메모리 정리
                }
                resolve(addresses);
              }
            }
          });
        });
      }
      // 네이버 맵 생성 (수호이미지 위치)
      let mapOptions = {
        center: {lat: 37.4814474, lng: 126.880440},
        zoom: 17,
        zoomControl: true,
        zoomControlOptions: {
          position: naver.maps.Position.TOP_RIGHT
        },
        mapTypeControl: true
      }
      let map = new naver.maps.Map('map', mapOptions);
      mapOptions = null; // 메모리 정리
      // 마커 생성
      let markerOptions = {
        position: {lat: 37.4814128, lng: 126.8804387},
        map: map
      }
      let marker = new naver.maps.Marker(markerOptions);
      markerOptions = null; // 메모리 정리
      // 마커에 정보창 생성
      let infoWindowOptions = {
        content: /* html */`
          <div class="infoWnd">
            1. 지도에서 특정 위치를 클릭하면 해당 위치의 좌표 및 주소 정보를 확인하실 수 있습니다.<br>
            2. 좌측 상단 검색창에 건물 이름이나 주소를 검색한 후 목록에서 항목을 선택하여 해당 위치로 이동합니다.<br>
            3. 복사 버튼을 누르면 해당 위치의 좌표 및 주소 정보를 복사할 수 있습니다.
          </div>
        `
      }
      let infoWindow = new naver.maps.InfoWindow(infoWindowOptions);
      infoWindow.open(map, marker);
      infoWindowOptions = null; // 메모리 정리
      // 위도-경도 값 복사하는 함수
      function copyCoord(_lat, _lng) {
        let copyText = document.createElement('textarea');
        copyText.value = `{"lat":"${_lat}","lng":"${_lng}"}`;
        document.body.appendChild(copyText);
        copyText.select();
        document.execCommand('copy');
        document.body.removeChild(copyText);
        copyText = null; // 메모리 정리
      }
      // 주소를 복사하는 함수
      function copyAddress(_address) {
        let copyText = document.createElement('textarea');
        copyText.value = `${_address}`;
        document.body.appendChild(copyText);
        copyText.select();
        document.execCommand('copy');
        document.body.removeChild(copyText);
        copyText = null; // 메모리 정리
      }
      // 지도 클릭 이벤트 생성
      naver.maps.Event.addListener(map, 'click', function(e) {
        searchCoordinateToAddress(e.coord.y, e.coord.x)
        .then((res) => {
          // 기존 정보창 닫기
          infoWindow.close();
          // 클릭한 위치에 마커 생성
          marker.setPosition(e.coord);
          // 새로운 정보창 생성
          infoWindow.setContent(/* html */`
            <div class="infoWnd-coord">
              <div class="infoWnd-coord text">
                위도: ${e.coord.y}<br>
                경도: ${e.coord.x}<br>
                주소: ${res[0]}<br>
              </div>
              <div class="infoWnd-coord button">
                <button class="copy-button" onclick="copyCoord(${e.coord.y}, ${e.coord.x})">좌표 복사</button>
                <button class="copy-button" onclick="copyAddress('${res[0]}')">주소 복사</button>
              </div>
            </div>
          `);
          infoWindow.open(map, marker);
        })
        .catch((err) => {
          // 기존 정보창 닫기
          infoWindow.close();
          // 클릭한 위치에 마커 생성
          marker.setPosition(e.coord);
          // 새로운 정보창 생성
          infoWindow.setContent(/* html */`
            <div class="infoWnd-coord">
              <div class="infoWnd-coord text">
                위도: ${e.coord.y}<br>
                경도: ${e.coord.x}<br>
                주소: ${err.error}<br>
              </div>
              <div class="infoWnd-coord button">
                <button class="copy-button" onclick="copyCoord(${e.coord.y}, ${e.coord.x})">좌표 복사</button>
                <button class="copy-button" onclick="copyAddress('${err.error}')">주소 복사</button>
              </div>
            </div>
          `);
          infoWindow.open(map, marker);
        });
      });
      // 검색 이벤트 함수
      function searchAddress() {
        let query = document.querySelector('input#searchbox-input').value;
        // 입력 내용이 없을 경우
        if (query === '') {
          query = null; // 메모리 정리
          return;
        }
        naverSearchLocal(query)
        .then((res) => {
          if (res.data.errorCode) {
            // 에러인 경우 예외처리
            throw new Error(res.data.errorMessage);
          }
          document.querySelector('table.result tbody').innerHTML = '';
          if (res.data.items.length === 0) {
            // 검색 결과가 없을 경우
            // 지오코드를 이용하여 다시 검색
            searchAddressToCoordinate(query)
            .then((res) => {
              res.forEach(item => {
                let tr = document.createElement('tr');
                tr.innerHTML = /* html */`
                  <td>주소 검색</td>
                  <td>${item.jibunAddress}</td>
                `;
                tr.addEventListener('click', () => {
                  // 기존 정보창 닫기
                  infoWindow.close();
                  // 클릭한 위치에 마커 생성
                  let coord = new naver.maps.Point(item.x, item.y);
                  map.setCenter(coord);
                  marker.setPosition(coord);
                  infoWindow.setContent(/* html */`
                    <div class="infoWnd-coord">
                      <div class="infoWnd-coord text">
                        위도: ${coord.y}<br>
                        경도: ${coord.x}<br>
                        주소: ${item.jibunAddress}<br>
                      </div>
                      <div class="infoWnd-coord button">
                        <button class="copy-button" onclick="copyCoord(${coord.y}, ${coord.x})">좌표 복사</button>
                        <button class="copy-button" onclick="copyAddress('${item.jibunAddress}')">주소 복사</button>
                      </div>
                    </div>
                  `);
                  infoWindow.open(map, marker);
                  // 메모리 정리
                  coord = null;
                });
                document.querySelector('table.result tbody').appendChild(tr);
                // 메모리 정리
                tr = null;
              });
            })
            .catch((err) => {
              document.querySelector('table.result tbody').innerHTML = /* html */`
                <tr>
                  <td colspan="2">${err.error}</td>
                </tr>
              `;
            });
          } else {
            // 검색 결과가 있을 경우
            res.data.items.forEach(item => {
              let tr = document.createElement('tr');
              tr.innerHTML = /* html */`
                <td>${item.title.replace(/<[^>]*>/g, '')}</td>
                <td>${item.address}</td>
              `;
              tr.addEventListener('click', () => {
                // 기존 정보창 닫기
                infoWindow.close();
                // 클릭한 위치에 마커 생성
                let tm128 = new naver.maps.Point(item.mapx, item.mapy);
                let coord = naver.maps.TransCoord.fromTM128ToLatLng(tm128);
                map.setCenter(coord);
                marker.setPosition(coord);
                infoWindow.setContent(/* html */`
                  <div class="infoWnd-coord">
                    <div class="infoWnd-coord text">
                      위도: ${coord.y}<br>
                      경도: ${coord.x}<br>
                      주소: ${item.address}<br>
                    </div>
                    <div class="infoWnd-coord button">
                      <button class="copy-button" onclick="copyCoord(${coord.y}, ${coord.x})">좌표 복사</button>
                      <button class="copy-button" onclick="copyAddress('${item.address}')">주소 복사</button>
                    </div>
                  </div>
                `);
                infoWindow.open(map, marker);
                // 메모리 정리
                tm128 = null;
                coord = null;
              });
              document.querySelector('table.result tbody').appendChild(tr);
              // 메모리 정리
              tr = null;
            });
          }
          document.querySelector('div.result').style.display = 'grid';
        })
        .catch((err) => {
          document.querySelector('table.result tbody').innerHTML = /* html */`
            <tr>
              <td colspan="2">${err.toString()}</td>
            </tr>
          `;
          document.querySelector('div.result').style.display = 'grid';
        })
        .finally(() => {
          // 메모리 정리
          query = null;
        });
      }
      // 검색창 엔터키 입력 이벤트
      document.querySelector('input#searchbox-input').addEventListener('keydown', (e) => {
        if (e.keyCode === 13) {
          searchAddress();
        }
      });
      // 검색창 내용 입력 이벤트
      document.querySelector('input#searchbox-input').addEventListener('input', (e) => {
        if (e.target.value === '') {
          document.querySelector('button#searchbox-clear-button').style.visibility = 'hidden';
        } else {
          document.querySelector('button#searchbox-clear-button').style.visibility = 'visible';  
        }
      });
      // 검색창 내용 지우기 버튼 이벤트
      document.querySelector('button#searchbox-clear-button').addEventListener('click', () => {
        document.querySelector('input#searchbox-input').value = '';
        document.querySelector('button#searchbox-clear-button').style.visibility = 'hidden';
      });
      // 검색창 검색 버튼 이벤트
      document.querySelector('button#searchbox-button').addEventListener('click', () => {
        searchAddress();
      });
      // 검색결과 닫기 버튼 이벤트
      document.querySelector('button#result-close-button').addEventListener('click', () => {
        document.querySelector('table.result tbody').innerHTML = '';
        document.querySelector('div.result').style.display = 'none';
      });
    </script>
  </body>
</html>