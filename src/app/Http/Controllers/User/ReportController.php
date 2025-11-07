<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function daily(Request $request)
    {
        return view('user.reports.daily');
    }
}