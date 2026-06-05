<?php

use App\Livewire\BookingForm;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/book');
});

Route::get('/book', BookingForm::class);
