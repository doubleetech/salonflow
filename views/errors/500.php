<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Server Error</title>
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
            max-width: 500px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            text-align: center;
        }
        .error-code { font-size: 72px; font-weight: bold; color: #e74c3c; }
        .error-title { font-size: 24px; color: #2c3e50; margin: 16px 0 8px 0; }
        .error-message { color: #7f8c8d; font-size: 16px; margin: 16px 0 24px 0; }
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 12px 32px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
        }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">500</div>
        <h1 class="error-title">Server Error</h1>
        <p class="error-message">Something went wrong on our end. Please try again later.</p>
        <a href="javascript:history.back()" class="btn">Go Back</a>
    </div>
</body>
</html>