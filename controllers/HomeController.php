<?php

namespace App\Addons\pterodactylpanelapi\controllers;

use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HomeController
{
	public function index(Request $request): Response {
		return ApiResponse::success([], 'Welcome to my first plugins!',200);
	}
}