<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/event_tracking.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => 'Unknown error'];

try {
    $db = Database::getInstance()->getConnection();

    // Ensure tracking tables exist
    $db->exec("CREATE TABLE IF NOT EXISTS page_views (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        page_url TEXT NOT NULL,
        page_title TEXT,
        referrer TEXT,
        user_agent TEXT,
        ip_address VARCHAR(45),
        session_id VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY(user_id),
        KEY(session_id),
        KEY(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS search_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        search_query TEXT NOT NULL,
        search_type VARCHAR(255),
        filters TEXT,
        results_count INT DEFAULT 0,
        ip_address VARCHAR(45),
        user_agent TEXT,
        session_id VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY(user_id),
        KEY(session_id),
        KEY(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS page_sessions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        session_id VARCHAR(255) NOT NULL,
        page_url TEXT NOT NULL,
        start_time TIMESTAMP NULL,
        end_time TIMESTAMP NULL,
        time_spent_seconds INT DEFAULT 0,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY(user_id),
        KEY(session_id),
        KEY(page_url)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS click_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        event_type VARCHAR(255) NOT NULL,
        target_type VARCHAR(255),
        target_id INT NULL,
        page_url TEXT,
        metadata LONGTEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        session_id VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY(user_id),
        KEY(event_type),
        KEY(created_at),
        CONSTRAINT click_logs_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'track_page_view':
            // Track page view
            $page_url = $_POST['page_url'] ?? '';
            $page_title = $_POST['page_title'] ?? '';
            $referrer = $_POST['referrer'] ?? '';

            if (empty($page_url)) {
                $response['error'] = 'Page URL is required';
                break;
            }

            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            $session_id = session_id();
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $stmt = $db->prepare("
                INSERT INTO page_views (user_id, page_url, page_title, referrer, user_agent, ip_address, session_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $page_url, $page_title, $referrer, $user_agent, $ip_address, $session_id]);

            $response = ['success' => true, 'message' => 'Page view tracked'];
            break;

        case 'track_search':
            // Track search
            $search_query = trim($_POST['search_query'] ?? '');
            $search_type = $_POST['search_type'] ?? 'general';
            $filters = $_POST['filters'] ?? '{}';
            $results_count = intval($_POST['results_count'] ?? 0);

            if (empty($search_query)) {
                $response['error'] = 'Search query is required';
                break;
            }

            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            $session_id = session_id();
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $stmt = $db->prepare("
                INSERT INTO search_logs (user_id, search_query, search_type, filters, results_count, ip_address, user_agent, session_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $search_query, $search_type, $filters, $results_count, $ip_address, $user_agent, $session_id]);

            $response = ['success' => true, 'message' => 'Search tracked'];
            break;

        case 'start_page_session':
            // Start tracking time on page
            $page_url = $_POST['page_url'] ?? '';
            $page_start = $_POST['page_start'] ?? null;

            if (empty($page_url)) {
                $response['error'] = 'Page URL is required';
                break;
            }

            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            $session_id = session_id();
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            // Check if there's already an active session for this page
            $stmt = $db->prepare("
                SELECT id FROM page_sessions
                WHERE session_id = ? AND page_url = ? AND end_time IS NULL
            ");
            $stmt->execute([$session_id, $page_url]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing session with latest fields
                $stmt = $db->prepare("
                    UPDATE page_sessions
                    SET user_id = ?, ip_address = ?, user_agent = ?, start_time = COALESCE(?, start_time)
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $ip_address, $user_agent, $page_start, $existing['id']]);
            } else {
                // Create new session
                $stmt = $db->prepare("
                    INSERT INTO page_sessions (user_id, session_id, page_url, start_time, ip_address, user_agent)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $session_id, $page_url, $page_start, $ip_address, $user_agent]);
            }

            $response = ['success' => true, 'message' => 'Page session started'];
            break;

        case 'end_page_session':
            // End tracking time on page
            $page_url = $_POST['page_url'] ?? '';
            $time_spent = intval($_POST['time_spent_seconds'] ?? 0);
            $page_start = $_POST['page_start'] ?? null;
            $page_end = $_POST['page_end'] ?? null;

            if (empty($page_url)) {
                $response['error'] = 'Page URL is required';
                break;
            }

            $session_id = session_id();

            $stmt = $db->prepare("
                UPDATE page_sessions
                SET time_spent_seconds = ?, start_time = COALESCE(?, start_time), end_time = COALESCE(?, NOW())
                WHERE session_id = ? AND page_url = ? AND end_time IS NULL
            ");
            $stmt->execute([$time_spent, $page_start, $page_end, $session_id, $page_url]);

            $response = ['success' => true, 'message' => 'Page session ended'];
            break;

        case 'track_click':
            // Track click event
            $event_type = trim($_POST['event_type'] ?? '');
            $target_type = trim($_POST['target_type'] ?? '');
            $target_id = intval($_POST['target_id'] ?? 0);
            $page_url = $_POST['page_url'] ?? '';
            $metadata = $_POST['metadata'] ?? '';

            if (empty($event_type)) {
                $response['error'] = 'Event type is required';
                break;
            }

            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
            $session_id = session_id();
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $stmt = $db->prepare("INSERT INTO click_logs (user_id, event_type, target_type, target_id, page_url, metadata, ip_address, user_agent, session_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $event_type, $target_type, $target_id > 0 ? $target_id : null, $page_url, $metadata, $ip_address, $user_agent, $session_id]);

            $response = ['success' => true, 'message' => 'Click tracked'];
            break;

        case 'track_provider_view':
            // Track provider profile view
            $provider_id = intval($_POST['provider_id'] ?? 0);

            if ($provider_id <= 0) {
                $response['error'] = 'Invalid provider ID';
                break;
            }

            $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

            // Create provider_views table if not exists
            $db->exec("
                CREATE TABLE IF NOT EXISTS provider_views (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    provider_id INT NOT NULL,
                    user_id INT,
                    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    KEY(provider_id, viewed_at)
                )
            ");

            $stmt = $db->prepare("
                INSERT INTO provider_views (provider_id, user_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$provider_id, $user_id]);

            // Track event in centralized event_logs table
            trackEvent('view_provider', 'provider', $provider_id, [
                'provider_id' => $provider_id,
                'source' => 'profile_page'
            ]);

            $response = ['success' => true, 'message' => 'Provider view tracked'];
            break;

        default:
            $response['error'] = 'Invalid action';
            break;
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>