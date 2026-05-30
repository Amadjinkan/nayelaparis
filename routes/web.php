<?php

use Illuminate\Support\Facades\Route;

// Page d'accueil — sert le frontend
Route::get('/', function () {
    return file_get_contents(public_path('index.html'));
});
