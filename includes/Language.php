<?php
/**
 * Language class for handling translations
 */
class Language {
    private $translations = [];
    private $currentLanguage = 'en'; // Default language
    private $availableLanguages = ['en', 'fa', 'ps']; // Available languages
    private $langData = [];

    /**
     * Constructor
     * 
     * @param string $language Language code to initialize with
     */
    public function __construct($language = null) {
        // Set the current language from session, cookie, or parameter
        $this->currentLanguage = $this->determineLanguage($language);
        
        // Load language files
        $this->loadLanguageFiles($this->currentLanguage);
    }
    
    /**
     * Determine which language to use
     * 
     * @param string $language Language code parameter
     * @return string Language code to use
     */
    private function determineLanguage($language = null) {
        // Priority: 1. Parameter, 2. Session, 3. Cookie, 4. Default (en)
        if ($language !== null && in_array($language, $this->availableLanguages)) {
            return $language;
        }
        
        if (isset($_SESSION['language']) && in_array($_SESSION['language'], $this->availableLanguages)) {
            return $_SESSION['language'];
        }
        
        if (isset($_COOKIE['language']) && in_array($_COOKIE['language'], $this->availableLanguages)) {
            return $_COOKIE['language'];
        }
        
        return 'en';
    }
    
    /**
     * Load all language files for a specific language
     * 
     * @param string $language Language code
     */
    private function loadLanguageFiles($language) {
        // Path to language files
        $basePath = __DIR__ . '/languages/' . $language . '/';
        
        // Load common translations
        if (file_exists($basePath . 'common.php')) {
            $this->translations = require $basePath . 'common.php';
            $this->langData = $this->translations; // Store language metadata
        }
        
        // Add other language file types here as needed
        // For example: admin.php, frontend.php, etc.
    }
    
    /**
     * Get a translated string
     * 
     * @param string $key Translation key
     * @param array $placeholders Placeholders for string interpolation
     * @return string Translated string or key if not found
     */
    public function get($key, $placeholders = []) {
        // Check if the key exists in translations
        if (isset($this->translations[$key])) {
            $translation = $this->translations[$key];
            
            // Replace placeholders if any
            if (!empty($placeholders)) {
                foreach ($placeholders as $placeholder => $value) {
                    $translation = str_replace('{' . $placeholder . '}', $value, $translation);
                }
            }
            
            return $translation;
        }
        
        // Return the key if translation not found
        return $key;
    }
    
    /**
     * Set the current language
     * 
     * @param string $language Language code
     * @param bool $remember Whether to store in session and cookie
     * @return bool Success or failure
     */
    public function setLanguage($language, $remember = true) {
        if (in_array($language, $this->availableLanguages)) {
            $this->currentLanguage = $language;
            
            if ($remember) {
                // Store in session
                $_SESSION['language'] = $language;
                
                // Store in cookie for 30 days
                setcookie('language', $language, time() + (86400 * 30), '/');
            }
            
            // Reload language files
            $this->loadLanguageFiles($language);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get the current language code
     * 
     * @return string Current language code
     */
    public function getCurrentLanguage() {
        return $this->currentLanguage;
    }
    
    /**
     * Get the current language direction (ltr or rtl)
     * 
     * @return string Language direction
     */
    public function getDirection() {
        return isset($this->langData['dir']) ? $this->langData['dir'] : 'ltr';
    }
    
    /**
     * Check if current language is RTL
     * 
     * @return bool True if RTL, false otherwise
     */
    public function isRTL() {
        return $this->getDirection() === 'rtl';
    }
    
    /**
     * Get all available languages
     * 
     * @return array Available languages
     */
    public function getAvailableLanguages() {
        $languages = [];
        
        foreach ($this->availableLanguages as $code) {
            // Load language file to get name
            $filePath = __DIR__ . '/languages/' . $code . '/common.php';
            if (file_exists($filePath)) {
                $langData = require $filePath;
                $languages[$code] = isset($langData['lang_name']) ? $langData['lang_name'] : $code;
            } else {
                $languages[$code] = $code;
            }
        }
        
        return $languages;
    }
    
    /**
     * Add a new language to available languages
     * 
     * @param string $languageCode Language code
     * @return bool Success or failure
     */
    public function addLanguage($languageCode) {
        if (!in_array($languageCode, $this->availableLanguages)) {
            $this->availableLanguages[] = $languageCode;
            return true;
        }
        
        return false;
    }
} 