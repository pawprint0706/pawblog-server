<?php
  // DB 접속정보 및 API KEY
  require_once($_SERVER['DOCUMENT_ROOT']."/conf/secret.php");
?>
<!DOCTYPE html>
<html lang="ko">
  <head>
    <meta charset="utf-8">
    <title>이미지 좌표 확인하기</title>
    <!-- Panzoom (timmywil) -->
    <script src="https://unpkg.com/@panzoom/panzoom@4.5.1/dist/panzoom.min.js"></script>
    <!-- 스타일 -->
    <style>
      div#imageContainer {
        display: inline-block; /* 이미지의 크기에 따라 컨테이너 크기가 조정되게 함 */
        margin: 0;
        padding: 0;
        border: 1px solid black;
      }
      div#imageContent {
        margin: 0;
        padding: 0;
        border: 0;
      }
      div#imageContent img {
        display: block;
        margin: 0;
        padding: 0;
        border: 0;
        max-width: 100%;
        /* 이미지 드래그 차단 */
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
        -webkit-user-drag: none;
      }
      div#imageContainer div.coordinates {
        display: block;
        position: absolute;
        margin: 0;
        padding: 0;
        border: 2px solid white;
        border-radius: 50%;
        background-color: red;
        width: 3px;
        height: 3px;
        pointer-events: none; /* 마커가 클릭 이벤트를 가로채지 않도록 함 */
      }
      h1 {
        margin: 0;
        padding: 0;
        border: 0;
      }
      p {
        margin: 0;
        padding: 0;
        border: 0;
      }
      ul {
        margin: 0.5em 0 0.5em 0;
        border: 0;
      }
      li {
        margin: 0;
        padding: 0;
        border: 0;
      }
      input#imageUpload {
        margin: 0 0 5px 0;
        padding: 5px;
        border: 1px solid #cccccc;
        width: 50%;
      }
      p#showCoordinates {
        display: inline-block;
        margin: 0 0 0 1em;
        padding: 0;
        border: 0;
        font-weight: bold;
        color: blue;
      }
    </style>
  </head>
  <body>
    <h1>이미지 좌표 확인하기</h1>
    <p>이미지에서 특정 위치를 클릭하면 그 위치의 좌표 정보를 표시하고 클립보드에 복사합니다.</p>
    <ul>
      <li>Click : 좌표 확인 및 복사</li>
      <li>Shift + Wheel : 이미지 확대/축소</li>
      <li>Shift + Drag : 이미지 이동</li>
      <li>Shift + Double Click : 이미지 초기화</li>
    </ul>
    <!-- 이미지 불러오기 -->
    <input type="file" id="imageUpload" accept="image/*" />
    <!-- 이미지 좌표 표시 -->
    <p id="showCoordinates">X: 0, Y: 0</p>
    <br>
    <!-- 이미지 표시 영역 -->
    <div id="imageContainer">
      <div id="imageContent">
        <img src="" />
      </div>
    </div>
    <script>
      // Panzoom 초기화
      const imgContent = document.querySelector('div#imageContent');
      const panzoom = Panzoom(imgContent, {
        maxScale: 5,
        minScale: 1,
        contain: 'outside',
        noBind: true,
        cursor: 'crosshair'
      });
      // 이미지가 불러와지면 화면에 표시
      const imgTag = imgContent.querySelector('img');
      document.querySelector('input#imageUpload').addEventListener('change', (event) => {
        let reader = new FileReader();
        reader.onload = (event) => {
          imgTag.src = event.target.result;
        }
        reader.readAsDataURL(event.target.files[0]);
      });
      // Shift키를 누른 상태에서만 드래그 가능
      imgContent.addEventListener('pointerdown', (event) => {
        if (event.shiftKey) {
          panzoom.handleDown(event);
        }
      });
      imgContent.addEventListener('pointermove', (event) => {
        if (event.shiftKey) {
          panzoom.handleMove(event);
        }
      });
      imgContent.addEventListener('pointerup', (event) => {
        if (event.shiftKey) {
          panzoom.handleUp(event);
        }
      });
      // Shift키를 누른 상태에서만 휠로 확대/축소 가능
      imgContent.addEventListener('wheel', (event) => {
        if (event.shiftKey) {
          panzoom.zoomWithWheel(event);
        }
      });
      // Shift키를 누른 상태에서만 더블클릭 시 처음 상태로 복귀
      imgContent.addEventListener('dblclick', (event) => {
        if (event.shiftKey) {
          panzoom.reset();
        }
      });
      // 클릭한 위치에 마커 표시
      imgContent.addEventListener('click', (event) => {
        // 쉬프트키를 누르지 않은 상태에서만 좌표 표시
        if (!event.shiftKey) {
          // 기존 마커 삭제
          let markers = document.querySelectorAll('div.coordinates');
          for (let i = 0; i < markers.length; i++) {
            markers[i].parentNode.removeChild(markers[i]);
          }
          markers = null; // 메모리 정리
          // 이미지 상 클릭된 위치 좌표 구하기
          let posX = Math.round((event.offsetX / imgTag.clientWidth) * imgTag.naturalWidth);
          let posY = Math.round((event.offsetY / imgTag.clientHeight) * imgTag.naturalHeight);
          // 새로운 마커 등록
          let marker = document.createElement('div');
          marker.className = 'coordinates';
          marker.style.left = (event.offsetX - 3) + 'px';
          marker.style.top = (event.offsetY - 3) + 'px';
          imgContent.appendChild(marker);
          // 좌표 출력
          document.querySelector('p#showCoordinates').innerHTML = 'X: ' + posX + ', Y: ' + posY;
          // 좌표 복사
          let copyText = document.createElement('textarea');
          copyText.value = `{"x":"${posX}","y":"${posY}"}`;
          document.body.appendChild(copyText);
          copyText.select();
          document.execCommand('copy');
          document.body.removeChild(copyText);
          // 메모리 정리
          rect = null;
          x = null;
          y = null;
          marker = null;
          copyText = null;
        }
      });
    </script>
  </body>
</html>