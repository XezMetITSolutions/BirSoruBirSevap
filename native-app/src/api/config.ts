// Global API Configuration for the Mobile App

/**
 * PRODUCTION (Live Server)
 * Bir Soru Bir Sevap Canlı Sunucu Adresi
 */
const PRODUCTION_URL = 'https://birsorubirsevap.at/'; 

/**
 * DEVELOPMENT (Yerel Bilgisayar)
 * Bilgisayarınızın IP adresini buraya yazabilirsiniz (Örn: 192.168.1.50)
 */
const DEV_IP = 'localhost'; 
const DEVELOPMENT_URL = `http://${DEV_IP}/`;


// Aktif olan URL (Şu an CANLI sunucu seçili)
const BASE_URL = PRODUCTION_URL; 

export const API_ENDPOINTS = {
  MOBILE_INFO: `${BASE_URL}api_mobile.php`,
  QUESTIONS: `${BASE_URL}api_questions.php`,
};
