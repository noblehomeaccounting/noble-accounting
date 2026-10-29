<?php
// network/supabase-config.php
// Kinukuha ang Supabase keys sa .env (hindi naka-hardcode dito).
// Hinahanap ang .env sa: 1) isang folder sa labas ng web root  2) ROOT_PATH/.env

if (!function_exists('nhLoadEnv')) {
    function nhLoadEnv(): array
    {
        static $env = null;
        if ($env !== null) return $env;
        $env = [];
        foreach ([dirname(ROOT_PATH) . '/.env', ROOT_PATH . '/.env'] as $file) {
            if (!is_file($file) || !is_readable($file)) continue;
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
                if ($k !== '' && !array_key_exists($k, $env)) $env[$k] = $v;
            }
            break;
        }
        return $env;
    }
}

if (!function_exists('nhEnv')) {
    function nhEnv(string $key, string $default = ''): string
    {
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key) ?: null] as $v) {
            if ($v !== null && $v !== '') return (string) $v;
        }
        return nhLoadEnv()[$key] ?? $default;
    }
}

if (!defined('NH_SUPABASE_URL')) {
    define('NH_SUPABASE_URL', nhEnv('SUPABASE_URL', 'https://YOUR-PROJECT-REF.supabase.co'));
    define('NH_SUPABASE_ANON_KEY', nhEnv('SUPABASE_ANON_KEY'));      // publishable — puwede sa browser
    define('NH_SUPABASE_SECRET_KEY', nhEnv('SUPABASE_SECRET_KEY'));  // secret — PHP lang
    define('NH_REALTIME_SALT', nhEnv('REALTIME_SALT', 'change-me'));
}