<?php
// All routes are in api.php — no web middleware needed.
use Illuminate\Support\Facades\Route;
Route::get('/', fn() => redirect('/api/admin/login'));
