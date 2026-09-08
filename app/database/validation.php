<?php

function validateEmailFormat(string $value): ?string
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "Enter a valid email address.";
}

function validateRequired(string $value, string $label): ?string
{
    return trim($value) === '' ? "$label is required." : null;
}

function validateIntRange(string $value, string $label, int $min, int $max): ?string
{
    $ok = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);
    return $ok !== false ? null : "$label must be a whole number between $min and $max.";
}

function validateStringLength(string $value, string $label, int $max, int $min = 0): ?string
{
    $len = mb_strlen(trim($value));
    if ($min > 0 && $len < $min) {
        return "$label must be at least $min characters.";
    }
    if ($len > $max) {
        return "$label cannot exceed $max characters.";
    }
    return null;
}

function validatePositiveNumber($value, string $label, float $max = 999999.99): ?string
{
    $val = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($val === false || $val <= 0) {
        return "$label must be a positive number.";
    }
    if ($val > $max) {
        return "$label cannot exceed $max.";
    }
    return null;
}

function validateInArray(string $value, array $allowed, string $label): ?string
{
    if (!in_array($value, $allowed, true)) {
        return "Invalid $label selected.";
    }
    return null;
}

/**
 * CSRF Protection Helpers
 */
function getCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    $token = htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function validateCsrfToken(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || empty($token)) {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

function validateStudentInput(array $post): array
{
    $username = trim($post['username'] ?? '');
    $email    = trim($post['email'] ?? '');
    $age      = trim($post['age'] ?? '');

    $errors = array_filter([
        validateRequired($username, 'Username'),
        validateEmailFormat($email),
        validateIntRange($age, 'Age', 1, 120),
    ]);
    $errors = array_values($errors);

    if (empty($errors)) {
        $username = htmlspecialchars($username);
        $age      = (int) $age;
    }

    return [
        'errors' => $errors,
        'data'   => ['username' => $username, 'email' => $email, 'age' => $age],
    ];
}

