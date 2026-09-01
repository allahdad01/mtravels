<?php
/**
 * MTravels Translation Helper — backward-compatible wrapper.
 *
 * All translation logic has moved to translation_engine.php.
 * This file exists so the 22+ report files that do
 *   require_once __DIR__ . '/../../includes/translate_helper.php'
 * continue to work without changes.
 *
 * Functions provided (via translation_engine.php):
 *   translate_name($text, $targetLang)
 *   translate_place($text, $targetLang)
 *   translate_name_fields(&$data, $lang, $fields)
 *   translate_name_batch($names, $targetLang)
 *   is_arabic_script($text)
 *   is_latin_script($text)
 *   normalize_arabic_text($text)
 *   transliterate_to_arabic($text, $targetLang)
 *   is_effectively_translated($original, $result)
 *   lookup_word_script($text, $targetLang)
 *   normalize_name($text)
 *   lookup_dictionary($normalized)
 *   save_learned($englishName, $dari, $pashto, $source)
 */

require_once __DIR__ . '/translation_engine.php';
