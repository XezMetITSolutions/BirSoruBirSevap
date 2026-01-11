/**
 * Cookie Consent Manager
 * Handles the cookie banner and user preferences.
 */
(function() {
    'use strict';

    const COOKIE_NAME = 'cookie_consent';
    const CONSENT_VALUE = 'accepted';
    
    // Privacy Policy URL
    const PRIVACY_URL = 'https://islamfederasyonu.at/datenschutzerklaerung';

    const translations = {
        tr: {
            text: 'Sizlere daha iyi hizmet sunabilmek için sitemizde çerezler kullanılmaktadır.',
            linkText: 'KVKK ve Gizlilik Politikası',
            accept: 'Kabul Et',
            moreInfo: 'Daha Fazla Bilgi'
        },
        de: {
            text: 'Wir verwenden Cookies, um Ihnen den bestmöglichen Service zu bieten.',
            linkText: 'Datenschutzerklärung',
            accept: 'Akzeptieren',
            moreInfo: 'Mehr Info'
        }
    };

    function getLang() {
        return localStorage.getItem('lang') === 'de' ? 'de' : 'tr';
    }

    function hasConsent() {
        return document.cookie.split('; ').some((row) => row.startsWith(COOKIE_NAME + '=' + CONSENT_VALUE));
    }

    function setConsent() {
        const date = new Date();
        date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000)); // 1 year
        document.cookie = `${COOKIE_NAME}=${CONSENT_VALUE}; expires=${date.toUTCString()}; path=/; SameSite=Lax`;
        
        const banner = document.getElementById('cookie-banner');
        if (banner) {
            banner.classList.add('hide');
            setTimeout(() => banner.remove(), 500);
        }
    }

    function createBanner() {
        if (hasConsent()) return;

        const lang = getLang();
        const t = translations[lang];

        const banner = document.createElement('div');
        banner.id = 'cookie-banner';
        banner.innerHTML = `
            <div class="cookie-content">
                <p>
                    ${t.text} 
                    <a href="${PRIVACY_URL}" target="_blank" rel="noopener noreferrer">${t.linkText}</a>
                </p>
                <button id="accept-cookies" class="cookie-btn">${t.accept}</button>
            </div>
            <style>
                #cookie-banner {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    background: rgba(15, 23, 42, 0.95);
                    backdrop-filter: blur(10px);
                    color: white;
                    padding: 1rem;
                    z-index: 9999;
                    box-shadow: 0 -4px 6px rgba(0,0,0,0.1);
                    transform: translateY(100%);
                    animation: slideUp 0.5s forwards 0.5s;
                    font-family: 'Inter', system-ui, -apple-system, sans-serif;
                }
                
                #cookie-banner.hide {
                    transform: translateY(100%);
                    transition: transform 0.5s ease-in;
                }

                .cookie-content {
                    max-width: 1200px;
                    margin: 0 auto;
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1rem;
                }

                .cookie-content p {
                    margin: 0;
                    font-size: 0.95rem;
                    line-height: 1.5;
                    color: #e2e8f0;
                }

                .cookie-content a {
                    color: #38bdf8;
                    text-decoration: none;
                    font-weight: 500;
                    border-bottom: 1px solid transparent;
                    transition: border-color 0.2s;
                }

                .cookie-content a:hover {
                    border-color: #38bdf8;
                }

                .cookie-btn {
                    background: #068567;
                    color: white;
                    border: none;
                    padding: 0.5rem 1.5rem;
                    border-radius: 0.5rem;
                    cursor: pointer;
                    font-weight: 600;
                    font-size: 0.9rem;
                    transition: all 0.2s;
                    white-space: nowrap;
                }

                .cookie-btn:hover {
                    background: #059669;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                }

                @keyframes slideUp {
                    to { transform: translateY(0); }
                }

                @media (max-width: 768px) {
                    .cookie-content {
                        flex-direction: column;
                        text-align: center;
                    }
                    .cookie-btn {
                        width: 100%;
                    }
                }
            </style>
        `;

        document.body.appendChild(banner);
        document.getElementById('accept-cookies').addEventListener('click', setConsent);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createBanner);
    } else {
        createBanner();
    }

})();
