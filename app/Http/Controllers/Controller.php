<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

// Include centralized JWT authentication for all controllers
require_once app_path('auth-include.php');

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
