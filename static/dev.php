<?php
// SECURITY: Only allow dev switcher if .env exists (Local Dev)
if (!file_exists(__DIR__ . '/.env') && !file_exists(dirname(__DIR__) . '/.env')) {
    http_response_code(403);
    die('Access Denied: Dev tools are disabled in production.');
}

// Handle Login Selection
if (isset($_GET['user'])) {
    $user = $_GET['user'];
    // Set cookie for 30 days with explicit SameSite for Puppeteer compatibility
    setcookie('mock_mail', $user, [
        'expires' => time() + (86400 * 30),
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => false,
        'samesite' => 'Lax'  // Lax allows cookies in top-level navigation
    ]);
    header("Location: ./");
    exit;
}

// Handle Logout / Reset
if (isset($_GET['reset'])) {
    setcookie('mock_mail', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
    header("Location: ./");
    exit;
}

$currentUser = $_COOKIE['mock_mail'] ?? $_SERVER['mail'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dev Login Switcher</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Dev User Switcher</h1>
        <p class="text-slate-500 mb-6">Current User: <span class="font-mono bg-slate-100 px-2 py-1 rounded text-indigo-600"><?php echo htmlspecialchars($currentUser); ?></span></p>
        
        <div class="space-y-3">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Admin Users:</p>

            <a href="?user=admin@stonybrook.edu" class="block w-full p-4 border-2 border-purple-300 bg-purple-50 rounded-lg hover:bg-purple-100 hover:border-purple-400 transition-all flex items-center gap-3 group">
                <div class="w-8 h-8 rounded-full bg-purple-500 text-white flex items-center justify-center font-bold">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div>
                    <div class="font-bold text-purple-900">Admin</div>
                    <div class="text-xs text-purple-600">Full system access</div>
                </div>
            </a>

            <a href="?user=instructor@stonybrook.edu" class="block w-full p-4 border-2 border-purple-300 bg-purple-50 rounded-lg hover:bg-purple-100 hover:border-purple-400 transition-all flex items-center gap-3 group">
                <div class="w-8 h-8 rounded-full bg-purple-500 text-white flex items-center justify-center font-bold">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div>
                    <div class="font-bold text-purple-900">Instructor</div>
                    <div class="text-xs text-purple-600">Course management</div>
                </div>
            </a>
        </div>

        <div class="space-y-3 mt-6">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Student Teams:</p>

            <a href="?user=test_mail1@stonybrook.edu" class="block w-full p-4 border border-slate-200 rounded-lg hover:bg-indigo-50 hover:border-indigo-200 transition-all flex items-center gap-3 group">
                <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold group-hover:bg-indigo-600 group-hover:text-white transition-colors">1</div>
                <div class="font-medium text-slate-700">Team 1 (Primary)</div>
            </a>

            <a href="?user=test_mail2@stonybrook.edu" class="block w-full p-4 border border-slate-200 rounded-lg hover:bg-emerald-50 hover:border-emerald-200 transition-all flex items-center gap-3 group">
                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold group-hover:bg-emerald-600 group-hover:text-white transition-colors">2</div>
                <div class="font-medium text-slate-700">Team 2</div>
            </a>

            <a href="?user=test_mail3@stonybrook.edu" class="block w-full p-4 border border-slate-200 rounded-lg hover:bg-amber-50 hover:border-amber-200 transition-all flex items-center gap-3 group">
                <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center font-bold group-hover:bg-amber-600 group-hover:text-white transition-colors">3</div>
                <div class="font-medium text-slate-700">Team 3</div>
            </a>

             <a href="?user=test_mail4@stonybrook.edu" class="block w-full p-4 border border-slate-200 rounded-lg hover:bg-rose-50 hover:border-rose-200 transition-all flex items-center gap-3 group">
                <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center font-bold group-hover:bg-rose-600 group-hover:text-white transition-colors">4</div>
                <div class="font-medium text-slate-700">Team 4</div>
            </a>
        </div>

        <div class="mt-8 pt-6 border-t border-slate-100">
            <a href="?reset=1" class="text-sm text-red-500 hover:text-red-700 font-medium flex items-center gap-1 justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                Reset to Default (.htaccess)
            </a>
        </div>
    </div>
</body>
</html>
