<?php
/**
 * YOCOR Express Logistics - Front Controller & Router
 * Routes requests to dedicated controllers/pages in app/pages/
 */

// Determine the requested route/page
$page = isset($_GET['page']) ? trim(preg_replace('/\.php$/', '', $_GET['page'])) : '';

// Also support direct path if queried or fallback
if (empty($page)) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Normalize path relative to project folder
    $relative = trim(str_replace($scriptName, '', $uri), '/');
    $parts = explode('/', $relative);
    $first = $parts[0] ?? '';
    
    // If it ends with .php, strip it
    $cleanRoute = preg_replace('/\.php$/', '', $first);
    
    if (!empty($cleanRoute) && $cleanRoute !== 'index') {
        $page = $cleanRoute;
    }
}

// Available pages whitelist
$routes = [
    'services' => __DIR__ . '/app/pages/services.php',
    'tracking' => __DIR__ . '/app/pages/tracking.php',
    'quote'    => __DIR__ . '/app/pages/quote.php',
    'booking'  => __DIR__ . '/app/pages/booking.php',
    'contact'  => __DIR__ . '/app/pages/contact.php',
    'student'  => __DIR__ . '/app/pages/student.php',
    'success'  => __DIR__ . '/app/pages/success.php',
    'info'     => __DIR__ . '/app/pages/info.php',
    'customer_dashboard' => __DIR__ . '/app/pages/customer_dashboard.php',
    'payment'            => __DIR__ . '/app/pages/payment.php',
    'receipt'            => __DIR__ . '/app/pages/receipt.php',
    'admin_dashboard'    => __DIR__ . '/app/pages/admin_dashboard.php',
    'admin_services'     => __DIR__ . '/app/pages/admin_services.php',
    'process_booking'    => __DIR__ . '/process_booking.php',
    'terms'              => __DIR__ . '/app/pages/terms.php',
    'security'           => __DIR__ . '/app/pages/security.php',
    'admin_inquiries'    => __DIR__ . '/app/pages/admin_inquiries.php',
    'admin_booking_timeline' => __DIR__ . '/app/pages/admin_booking_timeline.php',
];

// If requesting a specific page from the route whitelist, load it
if (!empty($page) && isset($routes[$page])) {
    require $routes[$page];
    exit;
}

// Otherwise, render Home Page
$pageTitle = "YOCOR Express Logistic | Premier Global Logistics & Transport";
$activeNav = "home";

include __DIR__ . '/app/includes/header.php';
?>

<main class="flex-grow">
  <?php include __DIR__ . '/app/includes/sections/hero.php'; ?>
  <?php include __DIR__ . '/app/includes/sections/stats.php'; ?>
  <?php include __DIR__ . '/app/includes/sections/how-it-works.php'; ?>
  <?php include __DIR__ . '/app/includes/sections/mission.php'; ?>
  <?php include __DIR__ . '/app/includes/sections/services.php'; ?>
  <?php include __DIR__ . '/app/includes/sections/why-us.php'; ?>
  <?php include __DIR__ . '/app/includes/sections/quote.php'; ?>
</main>

<?php include __DIR__ . '/app/includes/footer.php'; ?>