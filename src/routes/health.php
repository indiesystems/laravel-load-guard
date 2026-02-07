<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $manager = app('load-guard');
    $status = $manager->getStatus();

    return response()->json($status, $status['can_accept_work'] ? 200 : 503);
});
