<!DOCTYPE html>
<html>
<head>
    <title>Debug Overlay</title>
    <script src="https://cdn.jsdelivr.net/npm/@unocss/runtime@0.58.5/uno.global.js"></script>
</head>
<body class="bg-gray-900 text-white p-8">
    <h1 class="text-2xl mb-4">Overlay Class Test</h1>

    <div class="mb-8">
        <h2 class="text-xl mb-2">Test 1: hidden + flex</h2>
        <div class="hidden flex items-center justify-center p-4 bg-red-500">
            This should be HIDDEN (but might show due to flex)
        </div>
        <p class="text-sm text-gray-400 mt-2">If you see red box above, that's the bug!</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl mb-2">Test 2: just hidden</h2>
        <div class="hidden p-4 bg-green-500">
            This should be HIDDEN
        </div>
        <p class="text-sm text-gray-400 mt-2">Should NOT see green box above</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl mb-2">Test 3: flex without hidden</h2>
        <div class="flex items-center justify-center p-4 bg-blue-500">
            This should be VISIBLE
        </div>
        <p class="text-sm text-gray-400 mt-2">Should see blue box above</p>
    </div>

    <div class="mt-8">
        <h2 class="text-xl mb-4">Game Over Overlay Classes:</h2>
        <code class="text-xs bg-gray-800 p-2 block">
            <?php echo htmlspecialchars('class="hidden fixed inset-0 bg-gray-900 z-[150] flex flex-col"'); ?>
        </code>
    </div>

    <script>
        // Check computed styles
        setTimeout(() => {
            const test1 = document.querySelector('.mb-8:nth-of-type(1) > div:nth-of-type(2)');
            console.log('Test 1 (hidden + flex) computed display:', window.getComputedStyle(test1).display);
        }, 500);
    </script>
</body>
</html>
