<?php

/**
 * ErrorHandler
 */
class ErrorHandler
{
    /**
     * Register all error handlers
     */
    public static function register(): void
    {
        // Set error reporting level
        error_reporting(E_ALL);
        
        // Convert errors to exceptions
        set_error_handler([self::class, 'handleError']);
        
        // Handle exceptions
        set_exception_handler([self::class, 'handleException']);
        
        // Handle shutdown errors (fatal errors)
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Convert PHP errors to ErrorException
     */
    public static function handleError($severity, $message, $file, $line): void
    {
        if (!(error_reporting() & $severity)) {
            return;
        }
        
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle all uncaught exceptions
     */
    public static function handleException($exception): void
    {
        // Log the error
        self::logError($exception);
        
        // Determine if this is an AJAX request
        $isAjax = self::isAjaxRequest();
        
        // Get HTTP status code
        $statusCode = $exception->getCode() ?: 500;
        if ($statusCode < 400 || $statusCode > 599) {
            $statusCode = 500;
        }
        
        http_response_code($statusCode);
        
        if ($isAjax) {
            // Return JSON error response for AJAX requests
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => self::getUserFriendlyMessage($exception, $statusCode),
                'code' => $statusCode
            ]);
            exit;
        }
        
        // Show user-friendly error page
        self::renderErrorPage($exception, $statusCode);
        exit;
    }

    /**
     * Handle fatal shutdown errors
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
            $exception = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            
            self::handleException($exception);
        }
    }

    /**
     * Log error to file
     */
    private static function logError($exception): void
    {
        $logFile = __DIR__ . '/../logs/error.log';
        $logDir = dirname($logFile);
        
        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $message = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
            str_repeat('-', 80)
        );
        
        error_log($message, 3, $logFile);
    }

    /**
     * Get user-friendly error message
     */
    private static function getUserFriendlyMessage($exception, int $statusCode): string
    {
        // Check if we're in development mode
        $isDev = defined('ENV') && ENV === 'development';
        
        if ($isDev) {
            // Show detailed error in development
            return sprintf(
                '%s: %s in %s line %d',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
        }
        
        // User-friendly messages based on status code
        $messages = [
            400 => 'The request could not be processed. Please check your input.',
            401 => 'You must be logged in to access this page.',
            403 => 'You do not have permission to access this page.',
            404 => 'The page you requested could not be found.',
            405 => 'The requested method is not allowed.',
            500 => 'Something went wrong on our end. Please try again later.',
            503 => 'The service is currently unavailable. Please try again later.',
        ];
        
        // Database specific messages
        if ($exception instanceof PDOException) {
            return 'A database error occurred. Please try again later.';
        }
        
        return $messages[$statusCode] ?? 'An unexpected error occurred. Please try again later.';
    }

    /**
     * Render error page
     */
    private static function renderErrorPage($exception, int $statusCode): void
    {
        $isDev = defined('ENV') && ENV === 'development';
        
        // Get error details for development
        $errorDetails = $isDev ? [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'type' => get_class($exception)
        ] : null;
        
        // Try to load custom error page
        $errorViewPath = __DIR__ . '/../views/errors/' . $statusCode . '.php';
        
        if (file_exists($errorViewPath)) {
            // Load custom error page
            require $errorViewPath;
            exit;
        }
        
        // Fallback error page
        self::renderFallbackErrorPage($statusCode, $isDev, $errorDetails);
        exit;
    }

    /**
     * Render fallback error page (if custom page doesn't exist)
     */
    private static function renderFallbackErrorPage(int $statusCode, bool $isDev, ?array $errorDetails): void
    {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $statusCode; ?> - Error</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                    background: #f5f6fa;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    padding: 20px;
                }
                .error-container {
                    background: white;
                    border-radius: 12px;
                    padding: 50px;
                    max-width: 600px;
                    width: 100%;
                    box-shadow: 0 4px 24px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .error-code {
                    font-size: 72px;
                    font-weight: bold;
                    color: #e74c3c;
                    line-height: 1;
                }
                .error-title {
                    font-size: 24px;
                    color: #2c3e50;
                    margin: 16px 0 8px 0;
                }
                .error-message {
                    color: #7f8c8d;
                    font-size: 16px;
                    margin: 16px 0 24px 0;
                    line-height: 1.6;
                }
                .btn {
                    display: inline-block;
                    background: #3498db;
                    color: white;
                    padding: 12px 32px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: 500;
                    transition: background 0.3s;
                }
                .btn:hover { background: #2980b9; }
                .dev-details {
                    margin-top: 30px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 6px;
                    text-align: left;
                    font-size: 13px;
                    font-family: 'Courier New', monospace;
                    overflow-x: auto;
                    color: #2c3e50;
                }
                .dev-details strong { color: #e74c3c; }
                .dev-details .file { color: #2980b9; }
                .dev-details .line { color: #27ae60; }
                .dev-details .trace {
                    background: #2c3e50;
                    color: #ecf0f1;
                    padding: 12px;
                    border-radius: 4px;
                    margin-top: 10px;
                    font-size: 12px;
                    overflow-x: auto;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                }
                @media (max-width: 480px) {
                    .error-container { padding: 30px 20px; }
                    .error-code { font-size: 48px; }
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-code"><?php echo $statusCode; ?></div>
                <h1 class="error-title">
                    <?php
                    $titles = [
                        400 => 'Bad Request',
                        401 => 'Unauthorized',
                        403 => 'Forbidden',
                        404 => 'Page Not Found',
                        405 => 'Method Not Allowed',
                        500 => 'Server Error',
                        503 => 'Service Unavailable',
                    ];
                    echo $titles[$statusCode] ?? 'Something went wrong';
                    ?>
                </h1>
                <p class="error-message">
                    <?php
                    $messages = [
                        400 => 'The request could not be processed. Please check your input and try again.',
                        401 => 'You need to be logged in to access this page.',
                        403 => 'You don\'t have permission to view this page.',
                        404 => 'The page you are looking for might have been moved or deleted.',
                        405 => 'The request method is not supported for this endpoint.',
                        500 => 'We\'re experiencing technical difficulties. Please try again later.',
                        503 => 'The service is temporarily unavailable. We\'ll be back soon.',
                    ];
                    echo $messages[$statusCode] ?? 'An unexpected error occurred. Please try again later.';
                    ?>
                </p>
                <a href="javascript:history.back()" class="btn">Go Back</a>
                
                <?php if ($isDev && $errorDetails): ?>
                <div class="dev-details">
                    <strong>Error Details (Development Mode):</strong><br>
                    <span class="file">Type:</span> <?php echo htmlspecialchars($errorDetails['type']); ?><br>
                    <span class="file">Message:</span> <?php echo htmlspecialchars($errorDetails['message']); ?><br>
                    <span class="file">File:</span> <?php echo htmlspecialchars($errorDetails['file']); ?><br>
                    <span class="line">Line:</span> <?php echo $errorDetails['line']; ?><br>
                    <div class="trace"><?php echo htmlspecialchars($errorDetails['trace']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Check if request is AJAX
     */
    private static function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}