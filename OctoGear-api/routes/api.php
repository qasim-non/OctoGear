<?php



use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working!']);
});

// Example: Fetching a user (requires authentication middleware)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
