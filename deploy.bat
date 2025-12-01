@echo off
REM Manuel FTP Deployment Script for BirSoruBirSevap
REM Windows için basit deployment scripti

echo ========================================
echo Bir Soru Bir Sevap - FTP Deployment
echo ========================================
echo.

REM FTP bilgilerini buraya girin
set FTP_SERVER=ftp.example.com
set FTP_USER=your_username
set FTP_PASS=your_password

echo.
echo Dosyalar FTP sunucuya yükleniyor...
echo.

REM WinSCP veya FileZilla ile manuel olarak:
REM 1. FileZilla indirin: https://filezilla-project.org/
REM 2. Sunucu adresi: %FTP_SERVER%
REM 3. Kullanıcı adı: %FTP_USER%
REM 4. Şifre: %FTP_PASS%
REM 5. Sol taraftan yerel klasörü seçin
REM 6. Sağ taraftan FTP klasörünü seçin
REM 7. Tüm dosyaları seçip Upload yapın

echo.
echo FileZilla kullanarak:
echo 1. https://filezilla-project.org/download.php?type=client linkinden indirin
echo 2. Host: %FTP_SERVER%
echo 3. Username: %FTP_USER%
echo 4. Password: %FTP_PASS%
echo 5. Port: 21
echo 6. Quickconnect yapın
echo 7. Sol taraftan proje klasörünü seçin
echo 8. Sağ taraftan FTP klasörüne gidin
echo 9. Yüklemek istediğiniz dosyaları sürükleyip bırakın
echo.
pause

