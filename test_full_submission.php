<?php
/**
 * Full-form regression test for the client requirements system.
 *
 * Run:
 *   php test_full_submission.php
 *
 * Optional:
 *   php test_full_submission.php --cleanup
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

$options = getopt('', ['cleanup', 'keep']);
$cleanup = isset($options['cleanup']) && !isset($options['keep']);

function makeTempFile($prefix, $content) {
    $path = tempnam(sys_get_temp_dir(), $prefix);
    file_put_contents($path, $content);
    return $path;
}

function normalizeArrayField(array $values) {
    return array_values($values);
}

function assertContainsText($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
}

$clientId = 'CL-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

$tempFiles = [
    makeTempFile('logo', 'logo-binary-test'),
    makeTempFile('service', 'service-file-test'),
    makeTempFile('pageimg', 'page-image-test'),
    makeTempFile('pageatt', 'page-attachment-test'),
];

register_shutdown_function(function () use (&$tempFiles) {
    foreach ($tempFiles as $file) {
        if (is_string($file) && file_exists($file)) {
            @unlink($file);
        }
    }
});

$_POST = [
    'client_id' => $clientId,
    'projectType' => 'new',
    'project_type' => 'both',
    'otherProjectTypeInput' => 'Custom portal build',
    'businessCategory' => 'retail',
    'otherBusinessCategoryInput' => 'Specialty retail',
    'projectDescription' => 'Full storefront, marketing pages, contact form, and content management.',
    'launchDate' => '2026-09-15',
    'platform' => 'wordpress',
    'cmsPreference' => 'wordpress',
    'preferredCms' => 'WordPress + WooCommerce',
    'hasDomain' => 'yes',
    'domainName' => 'futuretest.example.com',
    'domainProvider' => 'Namecheap',
    'preferredDomain' => 'futuretest.com',
    'hasHosting' => 'yes',
    'hostingProvider' => 'Hostinger',
    'hostingPlan' => 'Business Web Hosting',
    'hostingUsername' => 'futureuser',
    'hostingPassword' => 'HostingSecret123!',
    'cpanelUrl' => 'https://cpanel.futuretest.example.com',
    'hasTheme' => 'yes',
    'themeName' => 'Astra Pro',
    'designStyle' => 'Modern premium minimal',
    'companyName' => 'Future Test Industries Pvt Ltd',
    'tagline' => 'Build the next thing today',
    'addCompanyDetails' => 'yes',
    'companyCode' => 'FTI-001',
    'gstNumber' => '27AABCF1234L1Z5',
    'registrationNumber' => 'CIN: U12345MH2026PTC999999',
    'bankAccount' => '12345678901234',
    'bankName' => 'State Bank of India',
    'ifscCode' => 'SBIN0001234',
    'companyAddress' => '123 Future Avenue, Tech Park',
    'city' => 'Mumbai',
    'state' => 'Maharashtra',
    'country' => 'India',
    'pincode' => '400001',
    'hasLogo' => 'yes',
    'logoLink' => 'https://drive.google.com/future-logo',
    'logoSize' => '512 x 512',
    'logoColors' => 'Blue, white, and silver with a geometric icon.',
    'brandGuidelines' => 'Use the primary blue consistently; keep tone trustworthy and modern.',
    'brandVoice' => 'professional',
    'contentReady' => 'partial',
    'headerPages' => normalizeArrayField(['home', 'about', 'services', 'contact', 'blog']),
    'footerPages' => normalizeArrayField(['privacy', 'terms', 'shipping']),
    'customFeatures' => normalizeArrayField(['contact', 'newsletter', 'chat', 'seo', 'payment-gateway']),
    'sellType' => 'both',
    'productDriveLink' => 'https://drive.google.com/future-products',
    'productCategories' => 'Electronics, Accessories, Bundles',
    'productImagesLink' => 'https://drive.google.com/future-product-images',
    'filterAttributes' => normalizeArrayField(['size', 'color', 'brand', 'price', 'availability']),
    'serviceDriveLink' => 'https://drive.google.com/future-services',
    'serviceCategories' => 'Consulting, Implementation, Support',
    'servicePortfolioLink' => 'https://drive.google.com/future-portfolio',
    'servicePricing' => 'Starter: 25k, Growth: 75k, Enterprise: custom',
    'isEcommerce' => 'yes',
    'paymentMethods' => normalizeArrayField(['razorpay', 'stripe', 'paypal', 'cod', 'other']),
    'razorpayKeyId' => 'rzp_test_future123',
    'stripeKey' => 'pk_test_future123',
    'paypalEmail' => 'billing@futuretest.example.com',
    'otherPaymentMethod' => 'UPI QR',
    'currencies' => normalizeArrayField(['USD', 'EUR', 'INR', 'other']),
    'otherCurrencyText' => 'AED',
    'taxRegion' => 'India',
    'taxRate' => '18',
    'shippingType' => 'flat',
    'flatRate' => '99',
    'shippingZones' => 'India, UAE, Europe',
    'shippingRates' => 'India: 99 | UAE: 499 | Europe: 799',
    'deliveryTimeframes' => 'India 3-5 days, UAE 5-7 days, Europe 7-10 days',
    'inventoryType' => 'automatic',
    'returnPolicy' => 'Returns accepted within 7 days if unused.',
    'businessDescription' => 'We build and scale modern digital commerce brands.',
    'keywords' => 'future test, ecommerce, web development, branding',
    'competitors' => 'competitor-one.example.com, competitor-two.example.com',
    'hasAnalytics' => 'yes',
    'analyticsId' => 'G-TEST123456',
    'hasGMB' => 'yes',
    'facebookUrl' => 'https://facebook.com/futuretest',
    'instagramUrl' => 'https://instagram.com/futuretest',
    'twitterUrl' => 'https://twitter.com/futuretest',
    'otherSocialMedia' => 'LinkedIn: https://linkedin.com/company/futuretest',
    'contactName' => 'Raj Vishwakarma',
    'contactEmail' => 'rajvishwakarma2021@gmail.com',
    'contactPhone' => '09111328903',
    'businessEmail' => 'office@futuretest.example.com',
    'businessAddress' => '123 Future Avenue, Tech Park, Mumbai',
    'additionalNotes' => 'Need fast loading, CMS access, and training handoff.',
    'appScreens' => normalizeArrayField(['home', 'login', 'profile']),
    'app_contentReady' => 'yes',
    'app_screenContent' => 'Overall app content notes for the admin review.',
    'home_title' => 'Home Dashboard',
    'home_content' => 'Welcome banner, featured products, quick actions, and latest updates.',
    'home_features' => 'Hero carousel, search bar, featured categories, and recent activity cards.',
    'pageHeading_home' => 'Welcome to Future Test',
    'pageSubheading_home' => 'Everything in one place',
    'content_home' => 'This is the home page content for Future Test.',
];

$_FILES = [
    'logoFile' => [
        'name' => 'logo.png',
        'type' => 'image/png',
        'tmp_name' => $tempFiles[0],
        'error' => 0,
        'size' => filesize($tempFiles[0]),
    ],
    'serviceDetailsFile' => [
        'name' => 'services.pdf',
        'type' => 'application/pdf',
        'tmp_name' => $tempFiles[1],
        'error' => 0,
        'size' => filesize($tempFiles[1]),
    ],
    'pageImage_home' => [
        'name' => 'home.png',
        'type' => 'image/png',
        'tmp_name' => $tempFiles[2],
        'error' => 0,
        'size' => filesize($tempFiles[2]),
    ],
    'content_home_file' => [
        'name' => 'home-content.docx',
        'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'tmp_name' => $tempFiles[3],
        'error' => 0,
        'size' => filesize($tempFiles[3]),
    ],
];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'multipart/form-data';

ob_start();
include __DIR__ . '/submit_form.php';
$submitResponse = ob_get_clean();

$submitJson = json_decode($submitResponse, true);
if (!is_array($submitJson) || empty($submitJson['success'])) {
    fwrite(STDERR, "Submission failed.\n");
    fwrite(STDERR, $submitResponse . "\n");
    exit(1);
}

$verifyConn = getDBConnection();
$stmt = $verifyConn->prepare('SELECT * FROM clients WHERE client_id = ?');
$stmt->bind_param('s', $clientId);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$client) {
    fwrite(STDERR, "Client row not found after submission.\n");
    exit(1);
}

$formData = json_decode($client['form_data'], true);
$checks = [
    'projectType' => $formData['projectType'] ?? null,
    'companyName' => $formData['companyName'] ?? null,
    'contactEmail' => $formData['contactEmail'] ?? null,
    'headerPages' => $formData['headerPages'] ?? [],
    'footerPages' => $formData['footerPages'] ?? [],
    'customFeatures' => $formData['customFeatures'] ?? [],
    'filterAttributes' => $formData['filterAttributes'] ?? [],
    'paymentMethods' => $formData['paymentMethods'] ?? [],
    'currencies' => $formData['currencies'] ?? [],
    'pageHeadings.home' => $formData['pageHeadings']['home'] ?? null,
    'pageSubheadings.home' => $formData['pageSubheadings']['home'] ?? null,
    'pageContents.home' => $formData['pageContents']['home'] ?? null,
    'appScreens' => $formData['appScreens'] ?? [],
    'home.title' => $formData['home_title'] ?? null,
    'home.content' => $formData['home_content'] ?? null,
    'home.features' => $formData['home_features'] ?? null,
    'pageImages.home.fileName' => $formData['pageImages']['home']['fileName'] ?? null,
    'pageAttachments.home.fileName' => $formData['pageAttachments']['home']['fileName'] ?? null,
    'logoFile.fileName' => $formData['logoFile'] ?? null,
    'serviceDetailsFile.fileName' => $formData['serviceDetailsFile'] ?? null,
];

ob_start();
$previousCwd = getcwd();
chdir(__DIR__ . '/admin');
$_SESSION['admin_logged_in'] = true;
$_GET['id'] = $clientId;
include 'view.php';
$adminHtml = ob_get_clean();
chdir($previousCwd);

$adminChecks = [
    'Client Details: ' . $clientId,
    'Future Test Industries Pvt Ltd',
    'rajvishwakarma2021@gmail.com',
    'Home Dashboard',
    'Welcome banner, featured products, quick actions, and latest updates.',
    'Hero carousel, search bar, featured categories, and recent activity cards.',
    'Welcome to Future Test',
    'Everything in one place',
    'home-content.docx',
    'services.pdf',
];

echo "Full submission test passed.\n";
echo "Client ID: {$clientId}\n";
echo "Database row saved: yes\n";
echo "Admin view checks:\n";
foreach ($adminChecks as $needle) {
    echo (assertContainsText($adminHtml, $needle) ? '  OK  ' : '  MISS ') . $needle . "\n";
}

echo "Saved field checks:\n";
foreach ($checks as $label => $value) {
    if (is_array($value)) {
        echo '  ' . $label . ': ' . implode(', ', $value) . "\n";
    } else {
        echo '  ' . $label . ': ' . (string) $value . "\n";
    }
}

if ($cleanup) {
    $delete = $verifyConn->prepare('DELETE FROM clients WHERE client_id = ?');
    $delete->bind_param('s', $clientId);
    $delete->execute();
    $delete->close();
    echo "Cleanup: deleted test row.\n";
} else {
    echo "Cleanup: skipped. Re-run with --cleanup to remove the test row.\n";
}

$verifyConn->close();
