<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/upload-cookies', function (Request $request) {
    
    $cookies = $request->input('cookies');


    if (!$cookies) {
        return response()->json(['error' => 'No cookies received'], 400);
    }



    // Save cookies to storage
    file_put_contents(storage_path('cookies.txt'), $cookies);

    return response()->json(['message' => 'Cookies uploaded successfully']);
});