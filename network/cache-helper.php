<?php
// network/cache-helper.php

function getCacheFile($key)
{
    return sys_get_temp_dir() . '/noble_' . md5($key) . '.cache';
}

function getCache($key, $ttl = 60)
{
    $file = getCacheFile($key);
    if (!file_exists($file))
        return false;
    if ((time() - filemtime($file)) > $ttl)
        return false;
    return file_get_contents($file);
}

// Atomic write: sa temp file muna, saka rename, para walang half-written na mababasa
function setCache($key, $data)
{
    $file = getCacheFile($key);
    $tmp = $file . '.' . uniqid('', true) . '.tmp';
    if (file_put_contents($tmp, $data) !== false) {
        if (!@rename($tmp, $file)) {
            @copy($tmp, $file); // fallback sa Windows
            @unlink($tmp);
        }
    }
}

function clearCache($key)
{
    $file = getCacheFile($key);
    if (file_exists($file))
        @unlink($file);
}