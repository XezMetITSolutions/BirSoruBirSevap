// Global API Configuration for the Mobile App

/**
 * PRODUCTION (All-Inkl veya Canlı Sunucu)
 * Eğer uygulamanız All-Inkl üzerindeyse BASE_URL kısmına alan adınızı yazın.
 * Örn: 'https://www.birsorubirsevap.com/'
 */
const PRODUCTION_URL = 'https://your-domain.com/'; // <-- Burayı kendi alan adınızla değiştirin

/**
 * DEVELOPMENT (Yerel Bilgisayar)
 * Bilgisayarınızın IP adresini yazın (Örn: 192.168.1.50)
 */
const DEV_IP = 'localhost'; 
const DEVELOPMENT_URL = `http://${DEV_IP}/`;


// Aktif olan URL'i seçin (Production'a geçerken PRODUCTION_URL kullanın)
const BASE_URL = DEVELOPMENT_URL; 

export const API_ENDPOINTS = {
  MOBILE_INFO: `${BASE_URL}api_mobile.php`,
  QUESTIONS: `${BASE_URL}api_questions.php`,
};
