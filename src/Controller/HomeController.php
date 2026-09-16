<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\View\View;

final class HomeController extends Controller
{
    public function index(Request $request): View
    {
        return $this->view('home', [
            'title' => 'Home',
            'phpVersion' => PHP_VERSION,
        ]);
    }
}
