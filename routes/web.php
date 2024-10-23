<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\VideoController;
use App\Models\Download;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    $videos=Download::where('user_id', auth()->id())->get() ?? [];
    return Inertia::render('Dashboard', [
        'canLogin' => Route::has('login'),
        'message' => '',
        'downloads'=>$videos
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    
    return redirect('/');
})->name('logout');


route::post("/videos/convert", [VideoController::class, 'store'])->name('videos.store');

route::post("/videos/download", [VideoController::class, 'download_video'])->name('videos.download');

Route::delete('/videos/{id}', [VideoController::class, 'destroy'])->name('videos.destroy');

require __DIR__.'/auth.php';
