<?php
/**
 * Download naming helpers for download.php (added in 1.0.1).
 *
 * Files are stored on disk as "<uniqid>-<original name>" (uploads) or
 * "<uniqid>-shipping-manifest-<id>.pdf" (generated manifests). The user
 * should never see the uniqid prefix, and the Content-Disposition header
 * must be strictly well-formed: Android's download manager (used by the
 * Home Assistant companion app) rejects anything it can't parse and falls
 * back to naming the file after the URL, i.e. "download.php".
 */

/**
 * The filename the user should see for a stored file.
 * 1. The original upload name recorded in Documents, if there is one
 * 2. Otherwise the stored name with its uniqid() prefix removed
 * 3. Otherwise the stored name as-is
 */
function resolveDownloadName(?PDO $pdo, $category, $storedName)
{
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("SELECT original_filename FROM Documents WHERE category = ? AND unique_filename = ?");
            $stmt->execute([$category, $storedName]);
            $original = $stmt->fetchColumn();
            if ($original) {
                return basename($original);
            }
        } catch (Exception $e) {
            // Fall through to the stored name
        }
    }

    // uniqid() gives 13 hex chars; uniqid($prefix, true) gives a prefix,
    // 14 hex chars and ".<digits>". Strip whichever form is present.
    return preg_replace('/^[A-Za-z_]*[0-9a-f]{13,14}(?:\.[0-9]+)?-/', '', basename($storedName));
}

/**
 * Make a filename safe for a quoted Content-Disposition value on every
 * client: ASCII only, no quotes/backslashes/control characters, and it
 * always keeps a file extension.
 */
function sanitizeDownloadName($name, $fallbackExt = '')
{
    $name = (string) $name;

    // Transliterate accents etc. to ASCII where the platform can, then drop
    // anything still outside printable ASCII
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($converted !== false) {
            $name = $converted;
        }
    }
    $name = preg_replace('/[^\x20-\x7E]/', '', $name);

    // Characters that would break a quoted-string or a filesystem path.
    // Separators are replaced (not stripped via basename) so a stray slash
    // in an original upload name can't truncate the download name.
    $name = str_replace(['"', '\\', '/', ':', '*', '?', '<', '>', '|'], '_', $name);
    $name = basename(trim($name, " ."));

    if ($name === '') {
        $name = 'download';
    }
    if ($fallbackExt !== '' && strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== strtolower($fallbackExt)) {
        $name .= '.' . $fallbackExt;
    }
    return $name;
}

/**
 * The exact Content-Disposition header value. Deliberately a single
 * quoted "filename" parameter and nothing after it: extra parameters or a
 * trailing semicolon make some download managers ignore the whole header.
 */
function downloadDispositionHeader($safeName)
{
    return 'attachment; filename="' . $safeName . '"';
}

/** MIME type for a file extension (lowercase, without the dot). */
function contentTypeForExtension($ext)
{
    $types = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'heic' => 'image/heic',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
    ];
    return $types[strtolower($ext)] ?? 'application/octet-stream';
}
