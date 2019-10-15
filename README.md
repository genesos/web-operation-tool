# 브라우징 자동화 도구
* 운영업무를 자동화
* 현재 웹사이트 열고 다운로드 지원  

# 환경 준비
* Choco 패키지 관리 설치
  * 시작 - PowerShell - 관리자 권한 실행
  * 아래내용을 붙여넣기
  * `Set-ExecutionPolicy Bypass -Scope Process -Force; iex ((New-Object System.Net.WebClient).DownloadString('https://chocolatey.org/install.ps1'))`
  * 설치를 기다림
* Selenium & Firefox 설치
  *  아래 명령 실행
  * `choco install javaruntime`
  * `choco install selenium`
  * `choco install selenium-gecko-driver`
  * `choco install firefox`
* Selenium 버그 수정
  * `C:\tools\selenium` 이동
  * `standalone.cmd` 파일 마우스 오른쪽 편집
  * `-enablePassThrough True` 문구 제거하고 저장

# 설치 방법
* `https://github.com/genesos/web-operation-tool/archive/master.zip`를 받아서 원하는 폴더에 해제

# 실행 방법
* `C:\tools\selenium\standalone.cmd` 실행
* 설치폴더의 `webtool.bat`로 원하는 파일(혹은 디렉토리) 드래그
  * 샘플 : `codes폴더`
