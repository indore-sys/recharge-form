<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

function sanitizePathSegment(string $value): string {
    $value = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value);
    $value = trim((string) $value, '-');
    return $value !== '' ? $value : 'file';
}

function getUploadSubdirectory(string $fieldName): string {
    // Page images: pageImage_{pageName} → page-images/{pageName}/
    if (preg_match('/^pageImage_(.+)$/', $fieldName, $matches)) {
        $pageName = sanitizePathSegment($matches[1]);
        return 'page-images/' . $pageName;
    }

    // Page attachments: content_{pageName}_file → page-attachments/{pageName}/
    if (preg_match('/^content_(.+)_file$/', $fieldName, $matches)) {
        $pageName = sanitizePathSegment($matches[1]);
        return 'page-attachments/' . $pageName;
    }

    // Screen media: {screenName}_media → screen-media/{screenName}/
    if (preg_match('/^(home|settings|profile|login|register|dashboard|search|cart|checkout|payment|notifications|help|support|messages|map|location|orders|favorites|wishlist|custom_.+)_media$/', $fieldName, $matches)) {
        $screenName = sanitizePathSegment($matches[1]);
        return 'screen-media/' . $screenName;
    }

    // App testimonials
    if (preg_match('/^app_testimonial_\d+_image$/', $fieldName)) {
        return 'testimonials';
    }

    // Gallery images (both website and app)
    if (preg_match('/^(app_)?gallery_\d+_image$/', $fieldName)) {
        return 'gallery';
    }

    // Logo files
    if (stripos($fieldName, 'logo') !== false) {
        return 'logos';
    }

    // Icon files
    if (stripos($fieldName, 'icon') !== false) {
        return 'icons';
    }

    // Documents and assets
    if (stripos($fieldName, 'asset') !== false || stripos($fieldName, 'document') !== false || stripos($fieldName, 'file') !== false) {
        return 'documents';
    }

    return 'misc';
}

function storeUploadedFile(string $clientId, string $fieldName, array $fileInfo): array {
    $tmpName = $fileInfo['tmp_name'] ?? '';
    $originalName = $fileInfo['name'] ?? '';
    $mimeType = $fileInfo['type'] ?? 'application/octet-stream';
    $size = (int) ($fileInfo['size'] ?? 0);
    $error = (int) ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new Exception("Upload failed for field {$fieldName}");
    }

    $baseDir = __DIR__ . '/uploads/clients/' . sanitizePathSegment($clientId);
    $subDir = $baseDir . '/' . getUploadSubdirectory($fieldName);
    if (!is_dir($subDir) && !mkdir($subDir, 0775, true) && !is_dir($subDir)) {
        throw new Exception("Could not create upload directory for {$fieldName}");
    }

    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $safeBaseName = sanitizePathSegment(pathinfo($originalName, PATHINFO_FILENAME));
    $uniqueName = sanitizePathSegment($fieldName) . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    if ($safeBaseName !== '') {
        $uniqueName .= '-' . $safeBaseName;
    }
    $finalFileName = $uniqueName . ($extension !== '' ? '.' . strtolower($extension) : '');
    $absolutePath = $subDir . '/' . $finalFileName;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        throw new Exception("Could not save uploaded file for {$fieldName}");
    }

    return [
        'fileName' => $originalName,
        'fileType' => $mimeType,
        'fileSize' => $size,
        'path' => 'uploads/clients/' . sanitizePathSegment($clientId) . '/' . getUploadSubdirectory($fieldName) . '/' . $finalFileName,
    ];
}

function readSubmissionData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $jsonInput = file_get_contents('php://input');
        $decoded = json_decode($jsonInput, true);

        if (!is_array($decoded)) {
            throw new Exception('Invalid JSON data received');
        }

        return $decoded;
    }

    return $_POST;
}

function collectUploadedFiles() {
    $files = [];

    foreach ($_FILES as $fieldName => $fileInfo) {
        if (is_array($fileInfo['name'])) {
            $count = count($fileInfo['name']);
            for ($i = 0; $i < $count; $i++) {
                if (!empty($fileInfo['name'][$i])) {
                    $files[$fieldName][$i] = [
                        'name' => $fileInfo['name'][$i],
                        'type' => $fileInfo['type'][$i],
                        'size' => $fileInfo['size'][$i],
                        'tmp_name' => $fileInfo['tmp_name'][$i],
                        'error' => $fileInfo['error'][$i],
                    ];
                }
            }
        } else {
            if (!empty($fileInfo['name'])) {
                $files[$fieldName] = [
                    'name' => $fileInfo['name'],
                    'type' => $fileInfo['type'],
                    'size' => $fileInfo['size'],
                    'tmp_name' => $fileInfo['tmp_name'],
                    'error' => $fileInfo['error'],
                ];
            }
        }
    }

    return $files;
}

function buildStructuredPayload(array $data, array $uploadedFiles, string $clientId) {
    $payload = $data;
    $payload['pageContents'] = [];
    $payload['pageHeadings'] = [];
    $payload['pageSubheadings'] = [];
    $payload['pageImages'] = [];
    $payload['pageAttachments'] = [];

    foreach ($data as $key => $value) {
        if (preg_match('/^(.*)\[\]$/', $key, $matches)) {
            $normalizedKey = $matches[1];
            $payload[$normalizedKey] = is_array($value) ? $value : [$value];
        }
        
        // Handle appScreens specifically - ensure it's always an array
        if ($key === 'appScreens' && !is_array($value)) {
            $payload['appScreens'] = is_string($value) && !empty($value) ? [$value] : [];
        }

        if (preg_match('/^pageHeading_(.+)$/', $key, $matches)) {
            $payload['pageHeadings'][$matches[1]] = $value;
        } elseif (preg_match('/^pageSubheading_(.+)$/', $key, $matches)) {
            $payload['pageSubheadings'][$matches[1]] = $value;
        } elseif (preg_match('/^content_(.+)$/', $key, $matches) && !preg_match('/^content_.+_file$/', $key)) {
            $payload['pageContents'][$matches[1]] = $value;
        }
    }

    foreach ($uploadedFiles as $fieldName => $fileInfo) {
        $storedFile = storeUploadedFile($clientId, $fieldName, $fileInfo);

        if (preg_match('/^pageImage_(.+)$/', $fieldName, $matches)) {
            $payload['pageImages'][$matches[1]] = [
                'fileName' => $storedFile['fileName'],
                'fileType' => $storedFile['fileType'],
                'fileSize' => $storedFile['fileSize'],
                'path' => $storedFile['path'],
            ];
        } elseif (preg_match('/^content_(.+)_file$/', $fieldName, $matches)) {
            $payload['pageAttachments'][$matches[1]] = [
                'fileName' => $storedFile['fileName'],
                'fileType' => $storedFile['fileType'],
                'fileSize' => $storedFile['fileSize'],
                'path' => $storedFile['path'],
            ];
        } else {
            $payload[$fieldName] = $storedFile['fileName'];
            $payload[$fieldName . '_type'] = $storedFile['fileType'];
            $payload[$fieldName . '_size'] = $storedFile['fileSize'];
            $payload[$fieldName . '_path'] = $storedFile['path'];
        }
    }

    return $payload;
}

try {
    error_log('=== FORM SUBMISSION START ===');
    error_log('Request Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
    error_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

    $data = readSubmissionData();
    $uploadedFiles = collectUploadedFiles();

    error_log('Submitted payload: ' . print_r($data, true));
    error_log('POST dump: ' . print_r($_POST, true));
    error_log('Uploaded files: ' . print_r(array_keys($uploadedFiles), true));

    $client_id = trim($data['client_id'] ?? '');
    if ($client_id === '') {
        $client_id = 'CL-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    }

    // Check both website and mobile app field names - prioritize contact name over company name
    $name = trim($data['contactName'] ?? $data['app_contactName'] ?? $data['companyName'] ?? $data['app_businessName'] ?? '');
    if ($name === '') {
        $name = 'Unknown Contact';
    }

    $email = trim($data['contactEmail'] ?? $data['app_contactEmail'] ?? '');
    $phone = trim($data['contactPhone'] ?? $data['app_contactPhone'] ?? '');
    $company_name = trim($data['companyName'] ?? $data['app_businessName'] ?? '');

    // Ensure multi-select fields are properly encoded as JSON arrays
    $multiSelectFields = [
        'businessGoals', 'pageExtras', 'serviceOperations', 'ecommerceOperations',
        'trustAssets', 'legalNeeds', 'leadDestinations', 'app_features',
        'app_target_platforms', 'app_deployment_stores', 'app_payment_methods',
        'appScreens', 'openDays', 'filterAttributes'
    ];

    foreach ($multiSelectFields as $field) {
        if (isset($data[$field])) {
            if (is_string($data[$field])) {
                // If it's a JSON string, decode it
                $decoded = json_decode($data[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload[$field] = $decoded;
                } else {
                    // If it's a comma-separated string, split it
                    $payload[$field] = array_map('trim', explode(',', $data[$field]));
                }
            } elseif (is_array($data[$field])) {
                $payload[$field] = $data[$field];
            } else {
                $payload[$field] = [$data[$field]];
            }
        }
    }

    $payload = buildStructuredPayload($data, $uploadedFiles, $client_id);

    $conn = getDBConnection();

    $check_sql = "SELECT id FROM clients WHERE client_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('s', $client_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    $form_data_json = json_encode($payload);

    if ($result->num_rows > 0) {
        $sql = "UPDATE clients SET
                name = ?,
                email = ?,
                phone = ?,
                company_name = ?,
                form_data = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE client_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssss', $name, $email, $phone, $company_name, $form_data_json, $client_id);
    } else {
        $sql = "INSERT INTO clients (client_id, name, email, phone, company_name, form_data)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssss', $client_id, $name, $email, $phone, $company_name, $form_data_json);
    }

    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $stmt->error);
    }

    $stmt->close();
    $check_stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'Form submitted successfully',
        'client_id' => $client_id,
        'post_data' => $data,
        'post_data_print_r' => print_r($_POST, true),
        'files' => array_keys($uploadedFiles),
        'structured_keys' => [
            'pageContents' => array_keys($payload['pageContents']),
            'pageHeadings' => array_keys($payload['pageHeadings']),
            'pageSubheadings' => array_keys($payload['pageSubheadings']),
            'pageImages' => array_keys($payload['pageImages']),
            'pageAttachments' => array_keys($payload['pageAttachments']),
        ],
    ]);
} catch (Exception $e) {
    error_log('Form submission error: ' . $e->getMessage());
    error_log('Error trace: ' . $e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'error_line' => $e->getLine(),
            'error_file' => $e->getFile(),
            'post_data' => $_POST,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
        ]
    ]);
}
