<?php

/**
 * Csrf
 * Generates and validates per-session CSRF tokens.
 * Every state-changing form (POST) must include the hidden field
 * rendered by Csrf::field(), and every controller handling that
 * POST must call Csrf::verify($_POST['csrf_token'] ?? '') first.
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /** Echoes a ready-to-use hidden input for forms. */
    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    public static function verify(string $submittedToken): bool
    {
        if (empty($_SESSION['_csrf_token']) || $submittedToken === '') {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $submittedToken);
    }

    /** Call this from a controller; it stops the request cold on mismatch. */
    public static function verifyOrFail(string $submittedToken): void
    {
        if (!self::verify($submittedToken)) {
            http_response_code(419);
            die('Invalid or expired security token. Please refresh the page and try again.');
        }
    }
}
