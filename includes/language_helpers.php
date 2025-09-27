<?php
/**
 * Language helper functions
 */

// Initialize the language object
if (!function_exists('init_language')) {
    function init_language() {
        global $lang;
        
        if (!isset($lang) || !($lang instanceof Language)) {
            require_once __DIR__ . '/Language.php';
            $lang = new Language();
        }
        
        return $lang;
    }
}

// Translation function
if (!function_exists('__')) {
    function __($key, $placeholders = []) {
        global $lang;
        
        $lang = init_language();
        return $lang->get($key, $placeholders);
    }
}

// Get language direction
if (!function_exists('get_lang_dir')) {
    function get_lang_dir() {
        global $lang;
        
        $lang = init_language();
        return $lang->getDirection();
    }
}

// Check if current language is RTL
if (!function_exists('is_rtl')) {
    function is_rtl() {
        global $lang;
        
        $lang = init_language();
        return $lang->isRTL();
    }
}

// Get current language
if (!function_exists('get_current_lang')) {
    function get_current_lang() {
        global $lang;
        
        $lang = init_language();
        return $lang->getCurrentLanguage();
    }
}

// Get available languages
if (!function_exists('get_available_languages')) {
    function get_available_languages() {
        global $lang;
        
        $lang = init_language();
        return $lang->getAvailableLanguages();
    }
}

// Set language
if (!function_exists('set_language')) {
    function set_language($language_code, $remember = true) {
        global $lang;
        
        $lang = init_language();
        return $lang->setLanguage($language_code, $remember);
    }
}

// Add CSS class for RTL if needed
if (!function_exists('rtl_class')) {
    function rtl_class($additional_classes = '') {
        global $lang;
        
        $lang = init_language();
        $rtl_class = $lang->isRTL() ? 'rtl' : '';
        
        if (!empty($additional_classes)) {
            return $rtl_class . ' ' . $additional_classes;
        }
        
        return $rtl_class;
    }
}

// Add language switcher HTML
if (!function_exists('language_switcher')) {
    function language_switcher() {
        global $lang;
        
        $lang = init_language();
        $current_language = $lang->getCurrentLanguage();
        $available_languages = $lang->getAvailableLanguages();
        
        $html = '<div class="language-switcher">';
        
        foreach ($available_languages as $code => $name) {
            $active_class = ($code === $current_language) ? 'active' : '';
            $html .= '<a href="?lang=' . $code . '" class="lang-item ' . $active_class . '">' . $name . '</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
} 