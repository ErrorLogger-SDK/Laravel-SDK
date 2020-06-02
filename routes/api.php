<?php

use Illuminate\Support\Facades\Route;

Route::post('report', 'ErrorLoggerReportController@report');
