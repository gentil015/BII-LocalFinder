<?php
/**
 * Shared validation and security helpers for forms and API requests.
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

function getRequestValue(string $key, $default = '', ?string $method = null) {
    $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $source = strtoupper($method) === 'POST' ? $_POST : $_GET;

    if (!is_array($source) || !array_key_exists($key, $source)) {
        return $default;
    }

    $value = $source[$key];
    if (is_array($value)) {
        return $value;
    }

    return trim((string) $value);
}

function getJsonInput(): array {
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);
    return is_array($decoded) ? $decoded : [];
}

function validateRequiredField($value, string $fieldName, array &$errors, ?string $message = null): bool {
    $isEmpty = is_array($value) ? count($value) === 0 : trim((string) $value) === '';
    if ($isEmpty) {
        $errors[] = $message ?? ucfirst(str_replace('_', ' ', $fieldName)) . ' is required.';
        return false;
    }

    return true;
}

function validateEmailField($value, string $fieldName, array &$errors, ?string $message = null): bool {
    $email = trim((string) $value);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = $message ?? 'Please enter a valid email address.';
        return false;
    }

    return true;
}

function validatePasswordField($value, array &$errors, int $minLength = 8, bool $requireSpecialChars = false, string $fieldName = 'password'): bool {
    $password = (string) $value;
    $isValid = true;

    if (strlen($password) < $minLength) {
        $errors[] = 'Password must be at least ' . $minLength . ' characters long.';
        $isValid = false;
    }

    if ($requireSpecialChars && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
        $isValid = false;
    }

    return $isValid;
}

function ensureCsrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token = null): bool {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $expectedToken = $_SESSION['csrf_token'] ?? '';
    if ($expectedToken === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $expectedToken = $_SESSION['csrf_token'];
    }

    if (empty($token)) {
        return false;
    }

    return hash_equals($expectedToken, (string) $token);
}
