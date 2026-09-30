<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\DataSource\StoreDataSourceRequest;
use App\Http\Requests\Api\Admin\DataSource\UpdateDataSourceRequest;
use App\Models\DataSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataSourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DataSource::query();

        if ($request->filled('job_type')) {
            $query->where('job_type', $request->query('job_type'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('website_name', 'like', "%{$search}%")
                  ->orWhere('link', 'like', "%{$search}%");
            });
        }

        $dataSources = $query->latest()->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $dataSources,
        ]);
    }

    public function store(StoreDataSourceRequest $request): JsonResponse
    {
        $dataSource = DataSource::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data source created successfully.',
            'data'    => $dataSource,
        ], 201);
    }

    public function show(DataSource $dataSource): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $dataSource,
        ]);
    }

    public function update(UpdateDataSourceRequest $request, DataSource $dataSource): JsonResponse
    {
        $dataSource->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data source updated successfully.',
            'data'    => $dataSource,
        ]);
    }

    public function destroy(DataSource $dataSource): JsonResponse
    {
        $dataSource->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data source deleted successfully.',
        ]);
    }

    public function updateStatus(Request $request, DataSource $dataSource): JsonResponse
    {
        $request->validate(['status' => 'required|boolean']);

        $dataSource->update(['status' => $request->boolean('status')]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully.',
            'data'    => $dataSource,
        ]);
    }
}