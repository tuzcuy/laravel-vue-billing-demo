<?php

use Illuminate\Support\Facades\Route;

// Single-page app: every non-API route serves the Vue shell,
// and vue-router takes over on the client.
Route::view('/{any?}', 'app')->where('any', '^(?!api).*$');
