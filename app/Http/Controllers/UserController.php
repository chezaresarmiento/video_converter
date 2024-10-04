<?php
 
namespace App\Http\Controllers;
 
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
 
class UserController extends Controller
{
    /**
     * Show the profile for a given user.
     */
    public function show(): Response
    {
        return Inertia::render('User/show', [
            'user' => [
                'id' => 1,
                'name' => 'John Doe'
            ]
        ]);
    }
}