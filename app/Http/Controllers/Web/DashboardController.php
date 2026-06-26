<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'pulseUrl' => route('pulse'),
            'docsUrl' => route('scramble.docs.ui'),
        ]);
    }
}
