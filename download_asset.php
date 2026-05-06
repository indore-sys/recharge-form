<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'config.php';

$client_id = trim($_GET['client_id'] ?? '');
$type = trim($_GET['type'] ?? '');
$page = trim($_GET['page'] ?? '');
$field = trim($_GET['field'] ?? '');
$download = isset($_GET['download']) && $_GET['download'] === '1';

if ($client_id === '' || $type === '') {
    http_response_code(400);
    exit('Missing required parameters');
}

$conn = getDBConnection();
$stmt = $conn->prepare('SELECT form_data FROM clients WHERE client_id = ? LIMIT 1');
$stmt->bind_param('s', $client_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    http_response_code(404);
    exit('Client not found');
}

$form_data = json_decode($row['form_data'] ?? '{}', true);
if (!is_array($form_data)) {
    $form_data = [];
}

function stream_binary(string $binary, string $fileName, string $mimeType, bool $download): void
{
    header('Content-Type: ' . ($mimeType ?: 'application/octet-stream'));
    $disposition = $download ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($fileName ?: 'download') . '"');
    header('Content-Length: ' . strlen($binary));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $binary;
    exit;
}

function stream_base64(string $base64Data, string $fileName, string $mimeType, bool $download): void
{
    $binary = base64_decode($base64Data, true);
    if ($binary === false) {
        http_response_code(500);
        exit('Invalid file data');
    }
    stream_binary($binary, $fileName, $mimeType, $download);
}

function stream_relative_path(string $relativePath, string $fileName, string $mimeType, bool $download): void
{
    $cleanPath = ltrim($relativePath, '/');
    $absolutePath = realpath(__DIR__ . '/' . $cleanPath);
    $allowedRoot = realpath(__DIR__ . '/uploads');

    if ($absolutePath === false || $allowedRoot === false || strpos($absolutePath, $allowedRoot) !== 0 || !is_file($absolutePath) || !is_readable($absolutePath)) {
        http_response_code(404);
        exit('Stored file not found');
    }

    header('Content-Type: ' . ($mimeType ?: 'application/octet-stream'));
    $disposition = $download ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($fileName ?: basename($absolutePath)) . '"');
    header('Content-Length: ' . filesize($absolutePath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($absolutePath);
    exit;
}

function stream_asset_record($record, string $fallbackName, string $fallbackMime, bool $download): void
{
    if (is_array($record)) {
        if (!empty($record['path'])) {
            stream_relative_path((string) $record['path'], (string) ($record['fileName'] ?? $fallbackName), (string) ($record['fileType'] ?? $fallbackMime), $download);
        }
        if (!empty($record['data'])) {
            stream_base64((string) $record['data'], (string) ($record['fileName'] ?? $fallbackName), (string) ($record['fileType'] ?? $fallbackMime), $download);
        }
    } elseif (is_string($record) && $record !== '') {
        stream_base64($record, $fallbackName, $fallbackMime, $download);
    }
}

switch ($type) {
    case 'logo':
        if (!empty($form_data['logoFile_path'])) {
            stream_relative_path((string) $form_data['logoFile_path'], (string) ($form_data['logoFile'] ?? 'logo'), (string) ($form_data['logoFile_type'] ?? 'application/octet-stream'), $download);
        }
        $logo = $form_data['logoFile_data'] ?? null;
        stream_asset_record($logo, (string) ($form_data['logoFile'] ?? 'logo.jpg'), (string) ($form_data['logoFile_type'] ?? 'image/jpeg'), $download);
        break;

    case 'page-image':
        if ($page !== '' && !empty($form_data['pageImages'][$page])) {
            stream_asset_record($form_data['pageImages'][$page], $page . '.jpg', 'image/jpeg', $download);
        }
        break;

    case 'page-attachment':
        if ($page !== '' && !empty($form_data['pageAttachments'][$page])) {
            stream_asset_record($form_data['pageAttachments'][$page], $page . '-attachment', 'application/octet-stream', $download);
        }
        break;

    case 'service-details':
        if (!empty($form_data['serviceDetailsFile_path'])) {
            stream_relative_path((string) $form_data['serviceDetailsFile_path'], (string) ($form_data['serviceDetailsFile'] ?? 'service-details.pdf'), (string) ($form_data['serviceDetailsFile_type'] ?? 'application/pdf'), $download);
        }
        $serviceData = $form_data['serviceDetailsFile_data'] ?? null;
        stream_asset_record($serviceData, (string) ($form_data['serviceDetailsFile'] ?? 'service-details.pdf'), (string) ($form_data['serviceDetailsFile_type'] ?? 'application/pdf'), $download);
        break;

    case 'business-assets':
        $preferredField = !empty($form_data['app_businessAssetsFile_path']) || !empty($form_data['app_businessAssetsFile_data']) ? 'app_businessAssetsFile' : 'businessAssetsFile';
        if (!empty($form_data[$preferredField . '_path'])) {
            stream_relative_path((string) $form_data[$preferredField . '_path'], (string) ($form_data[$preferredField] ?? 'business-assets.pdf'), (string) ($form_data[$preferredField . '_type'] ?? 'application/pdf'), $download);
        }
        $businessAssetsData = $form_data[$preferredField . '_data'] ?? null;
        stream_asset_record($businessAssetsData, (string) ($form_data[$preferredField] ?? 'business-assets.pdf'), (string) ($form_data[$preferredField . '_type'] ?? 'application/pdf'), $download);
        break;

    case 'field':
        if ($field !== '') {
            if (!empty($form_data[$field . '_path'])) {
                stream_relative_path((string) $form_data[$field . '_path'], (string) ($form_data[$field] ?? $field), (string) ($form_data[$field . '_type'] ?? 'application/octet-stream'), $download);
            }
            $legacyData = $form_data[$field . '_data'] ?? null;
            stream_asset_record($legacyData, (string) ($form_data[$field] ?? $field), (string) ($form_data[$field . '_type'] ?? 'application/octet-stream'), $download);
        }
        break;
}

http_response_code(404);
exit('Asset not found');
