<?php
// admin/navigation/settings/translate.php

$translateVariant = $translateVariant ?? 'desktop';

// Language ng mismong system mo (hindi ito isasama sa includedLanguages)
$sourceLang = 'en';

// Mga madalas gamitin, lalabas muna sa taas ng dropdown
$popular = [
    'en'    => 'English',
    'tl'    => 'Filipino',
    'ceb'   => 'Cebuano',
    'ilo'   => 'Ilocano',
    'zh-CN' => '中文 (简体)',
    'zh-TW' => '中文 (繁體)',
    'ja'    => '日本語 (Japanese)',
    'ko'    => '한국어 (Korean)',
    'es'    => 'Español (Spanish)',
    'ar'    => 'العربية (Arabic)',
];

// Lahat ng iba pa (alphabetical sa English name)
$others = [
    'af' => 'Afrikaans', 'sq' => 'Albanian', 'am' => 'Amharic', 'hy' => 'Armenian',
    'az' => 'Azerbaijani', 'eu' => 'Basque', 'be' => 'Belarusian', 'bn' => 'Bengali',
    'bs' => 'Bosnian', 'bg' => 'Bulgarian', 'ca' => 'Catalan', 'hr' => 'Croatian',
    'cs' => 'Czech', 'da' => 'Danish', 'nl' => 'Dutch', 'eo' => 'Esperanto',
    'et' => 'Estonian', 'fi' => 'Finnish', 'fr' => 'French', 'gl' => 'Galician',
    'ka' => 'Georgian', 'de' => 'German', 'el' => 'Greek', 'gu' => 'Gujarati',
    'ht' => 'Haitian Creole', 'ha' => 'Hausa', 'haw' => 'Hawaiian', 'iw' => 'Hebrew',
    'hi' => 'Hindi', 'hmn' => 'Hmong', 'hu' => 'Hungarian', 'is' => 'Icelandic',
    'ig' => 'Igbo', 'id' => 'Indonesian', 'ga' => 'Irish', 'it' => 'Italian',
    'jw' => 'Javanese', 'kn' => 'Kannada', 'kk' => 'Kazakh', 'km' => 'Khmer',
    'ku' => 'Kurdish', 'ky' => 'Kyrgyz', 'lo' => 'Lao', 'la' => 'Latin',
    'lv' => 'Latvian', 'lt' => 'Lithuanian', 'lb' => 'Luxembourgish', 'mk' => 'Macedonian',
    'mg' => 'Malagasy', 'ms' => 'Malay', 'ml' => 'Malayalam', 'mt' => 'Maltese',
    'mi' => 'Maori', 'mr' => 'Marathi', 'mn' => 'Mongolian', 'my' => 'Myanmar (Burmese)',
    'ne' => 'Nepali', 'no' => 'Norwegian', 'ny' => 'Nyanja (Chichewa)', 'ps' => 'Pashto',
    'fa' => 'Persian', 'pl' => 'Polish', 'pt' => 'Portuguese', 'pa' => 'Punjabi',
    'ro' => 'Romanian', 'ru' => 'Russian', 'sm' => 'Samoan', 'gd' => 'Scots Gaelic',
    'sr' => 'Serbian', 'st' => 'Sesotho', 'sn' => 'Shona', 'sd' => 'Sindhi',
    'si' => 'Sinhala', 'sk' => 'Slovak', 'sl' => 'Slovenian', 'so' => 'Somali',
    'su' => 'Sundanese', 'sw' => 'Swahili', 'sv' => 'Swedish', 'tg' => 'Tajik',
    'ta' => 'Tamil', 'te' => 'Telugu', 'th' => 'Thai', 'tr' => 'Turkish',
    'uk' => 'Ukrainian', 'ur' => 'Urdu', 'uz' => 'Uzbek', 'vi' => 'Vietnamese',
    'cy' => 'Welsh', 'xh' => 'Xhosa', 'yi' => 'Yiddish', 'yo' => 'Yoruba', 'zu' => 'Zulu',
];
asort($others, SORT_NATURAL | SORT_FLAG_CASE);

// Auto-generate ang includedLanguages (lahat maliban sa source language)
$allCodes = array_diff(array_merge(array_keys($popular), array_keys($others)), [$sourceLang]);
$includedLanguages = implode(',', $allCodes);

// I-load lang once ang script kahit dalawang beses i-include (desktop + mobile)
$loadAssets = !defined('TRANSLATE_ASSETS_LOADED');
if ($loadAssets) define('TRANSLATE_ASSETS_LOADED', true);

// Helper para sa options (para hindi ulit-ulitin sa desktop/mobile)
$renderOptions = function () use ($popular, $others) {
    echo '<optgroup label="Popular">';
    foreach ($popular as $code => $name) {
        echo '<option value="' . htmlspecialchars($code) . '">' . htmlspecialchars($name) . '</option>';
    }
    echo '</optgroup><optgroup label="All languages">';
    foreach ($others as $code => $name) {
        echo '<option value="' . htmlspecialchars($code) . '">' . htmlspecialchars($name) . '</option>';
    }
    echo '</optgroup>';
};
?>

<?php if ($translateVariant === 'desktop'): ?>
    <div class="notranslate flex items-center gap-3 px-3 py-2 rounded-lg text-gray-500 text-sm" translate="no">
        <i class="fa-solid fa-globe w-4 text-center text-sm flex-shrink-0 cursor-pointer"
            data-tooltip="Language" onclick="if (sidebarCollapsed) toggleSidebar();"></i>
        <select class="lang-select sidebar-label flex-1 min-w-0 text-xs bg-gray-50 border border-gray-200 rounded-md px-2 py-1 text-gray-700 focus:outline-none focus:ring-1 focus:ring-orange-400">
            <?php $renderOptions(); ?>
        </select>
    </div>
<?php else: ?>
    <div class="notranslate flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-gray-500" translate="no">
        <i class="fa-solid fa-globe w-4 text-center text-sm flex-shrink-0"></i>
        <select class="lang-select flex-1 text-sm bg-gray-50 border border-gray-200 rounded-lg px-2 py-1.5 text-gray-700">
            <?php $renderOptions(); ?>
        </select>
    </div>
<?php endif; ?>

<?php if ($loadAssets): ?>
    <!-- Hidden Google Translate element -->
    <div id="google_translate_element" style="display:none;"></div>

    <style>
        body>.skiptranslate,
        .goog-te-banner-frame,
        #goog-gt-tt,
        .goog-tooltip,
        .goog-te-balloon-frame {
            display: none !important;
        }

        body {
            top: 0 !important;
            position: static !important;
        }

        .goog-text-highlight {
            background: none !important;
            box-shadow: none !important;
        }
    </style>

    <script>
        const SOURCE_LANG = <?= json_encode($sourceLang) ?>;
        const INCLUDED_LANGS = <?= json_encode($includedLanguages) ?>;

        function getCurrentLang() {
            const m = document.cookie.match(/(?:^|;\s*)googtrans=\/[^/]+\/([^;]+)/);
            return m ? decodeURIComponent(m[1]) : SOURCE_LANG;
        }

        function setLang(lang) {
            const host = location.hostname;
            if (lang === SOURCE_LANG) {
                const past = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/';
                document.cookie = past;
                document.cookie = past + '; domain=' + host;
                document.cookie = past + '; domain=.' + host;
            } else {
                const val = 'googtrans=/' + SOURCE_LANG + '/' + lang + '; path=/';
                document.cookie = val;
                document.cookie = val + '; domain=' + host;
            }
            location.reload();
        }

        function googleTranslateInit() {
            new google.translate.TranslateElement({
                pageLanguage: SOURCE_LANG,
                includedLanguages: INCLUDED_LANGS,
                autoDisplay: false
            }, 'google_translate_element');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const current = getCurrentLang();
            document.querySelectorAll('.lang-select').forEach(sel => {
                sel.value = current;
                sel.addEventListener('change', e => setLang(e.target.value));
            });
        });
    </script>
    <script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateInit"></script>
<?php endif; ?>