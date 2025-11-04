<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/upload-cookies', function (Request $request) {
    try {
        $cookies = $request->input('cookies');

        if (!$cookies) {
            return response()->json(['error' => 'No cookies received'], 400);
        }

        // Validate cookie format (should be Netscape format)
        if (!str_contains($cookies, '# Netscape HTTP Cookie File') &&
            !str_contains($cookies, '.youtube.com')) {
            return response()->json(['error' => 'Invalid cookie format. Please ensure cookies are in Netscape format and contain YouTube cookies.'], 400);
        }

        $cookiePath = storage_path('cookies.txt');

        // Create storage directory if it doesn't exist
        $storageDir = dirname($cookiePath);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        // Save cookies to storage with proper error handling
        $result = file_put_contents($cookiePath, $cookies);

        if ($result === false) {
            return response()->json(['error' => 'Failed to save cookies to storage. Please check file permissions.'], 500);
        }

        // Verify the file was created and is readable
        if (!file_exists($cookiePath) || !is_readable($cookiePath)) {
            return response()->json(['error' => 'Cookie file was not created properly or is not readable.'], 500);
        }

        // Log successful cookie upload
        \Log::info('YouTube cookies uploaded successfully', [
            'file_size' => filesize($cookiePath),
            'cookie_path' => $cookiePath
        ]);

        return response()->json([
            'message' => 'Cookies uploaded successfully',
            'file_size' => filesize($cookiePath),
            'path' => $cookiePath
        ]);

    } catch (\Exception $e) {
        \Log::error('Cookie upload failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json(['error' => 'Failed to upload cookies: ' . $e->getMessage()], 500);
    }
});