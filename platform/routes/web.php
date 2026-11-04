<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/index');

Route::view('/index', 'index')->name('index');
