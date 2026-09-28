<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BusinessCategoryController extends Controller
{
    public function index()
    {
        $categories = \App\Models\BusinessCategory::where('status', 1)->get(['id', 'name', 'status']);
        
        return response()->json([
            'status' => true,
            'message' => 'Business categories retrieved successfully.',
            'data' => $categories,
        ], 200);
    }
}
