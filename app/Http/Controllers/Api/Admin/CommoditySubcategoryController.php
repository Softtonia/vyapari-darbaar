<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommoditySubcategory\BulkDeleteCommoditySubcategoryRequest;
use App\Http\Requests\Admin\CommoditySubcategory\BulkUpdateCommoditySubcategoryStatusRequest;
use App\Http\Requests\Admin\CommoditySubcategory\ListCommoditySubcategoryRequest;
use App\Http\Requests\Admin\CommoditySubcategory\StoreCommoditySubcategoryRequest;
use App\Http\Requests\Admin\CommoditySubcategory\UpdateCommoditySubcategoryRequest;
use App\Http\Requests\Admin\CommoditySubcategory\UpdateCommoditySubcategoryStatusRequest;
use App\Http\Resources\CommoditySubcategoryListResource;
use App\Http\Resources\CommoditySubcategoryResource;
use App\Models\CommoditySubcategory;
use App\Services\CommoditySubcategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommoditySubcategoryController extends Controller
{
    /**
     * Display a paginated listing of commodity subcategories.
     */
    public function index(ListCommoditySubcategoryRequest $request, CommoditySubcategoryService $service): JsonResponse
    {
        $paginator = $service->listCommoditySubcategories($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategories fetched successfully.',
            'data' => [
                'items' => CommoditySubcategoryListResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Get lightweight, cached list of active commodity subcategories for dropdown options.
     */
    public function options(Request $request, CommoditySubcategoryService $service): JsonResponse
    {
        $commodityId = $request->query('commodity_id');
        $commodityId = ($commodityId !== null && $commodityId !== '') ? (int) $commodityId : null;

        $options = $service->getOptions($commodityId);

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategory options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created commodity subcategory.
     */
    public function store(StoreCommoditySubcategoryRequest $request, CommoditySubcategoryService $service, \App\Services\MediaService $mediaService): JsonResponse
    {
        $data = $request->validated();

        if ($request->has('media_id') || $request->hasFile('image') || $request->filled('image_url')) {
            $media = $mediaService->resolve(
                $request->input('media_id'),
                $request->file('image'),
                $request->input('image_url')
            );
            if ($media) {
                $data['media_id'] = $media->id;
            }
        }

        $subcat = $service->createCommoditySubcategory(
            $data,
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategory created successfully.',
            'data' => new CommoditySubcategoryResource($subcat),
        ], 201);
    }

    /**
     * Display the specified commodity subcategory detail.
     */
    public function show(CommoditySubcategory $commoditySubcategory): JsonResponse
    {
        $commoditySubcategory->load([
            'commodity:id,commodity_category_id,name,slug',
            'commodity.category:id,name,slug',
            'creator',
            'updater',
            'media',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategory retrieved successfully.',
            'data' => new CommoditySubcategoryResource($commoditySubcategory),
        ], 200);
    }

    /**
     * Update the specified commodity subcategory.
     */
    public function update(
        UpdateCommoditySubcategoryRequest $request,
        CommoditySubcategory $commoditySubcategory,
        CommoditySubcategoryService $service,
        \App\Services\MediaService $mediaService
    ): JsonResponse {
        try {
            $data = $request->validated();
            
            if ($request->has('media_id') || $request->hasFile('image') || $request->filled('image_url')) {
                $media = $mediaService->resolve(
                    $request->input('media_id'),
                    $request->file('image'),
                    $request->input('image_url')
                );
                if ($media) {
                    $data['media_id'] = $media->id;
                }
            }

            $updatedSubcategory = $service->updateCommoditySubcategory(
                $commoditySubcategory,
                $data,
                $request->user()?->id
            );

            return response()->json([
                'status' => true,
                'message' => 'Commodity subcategory updated successfully.',
                'data' => new CommoditySubcategoryResource($updatedSubcategory),
            ], 200);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'COMMODITY_SUBCATEGORY_IN_USE',
            ], 409);
        }
    }

    /**
     * Update the active/inactive status of a commodity subcategory.
     */
    public function updateStatus(
        UpdateCommoditySubcategoryStatusRequest $request,
        CommoditySubcategory $commoditySubcategory,
        CommoditySubcategoryService $service
    ): JsonResponse {
        $updatedSubcategory = $service->updateStatus(
            $commoditySubcategory,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategory status updated successfully.',
            'data' => new CommoditySubcategoryResource($updatedSubcategory),
        ], 200);
    }

    /**
     * Bulk update status for multiple commodity subcategories.
     */
    public function bulkStatus(
        BulkUpdateCommoditySubcategoryStatusRequest $request,
        CommoditySubcategoryService $service
    ): JsonResponse {
        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategories status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single commodity subcategory safely.
     */
    public function destroy(
        CommoditySubcategory $commoditySubcategory,
        CommoditySubcategoryService $service
    ): JsonResponse {
        try {
            $service->deleteCommoditySubcategory($commoditySubcategory);

            return response()->json([
                'status' => true,
                'message' => 'Commodity subcategory deleted successfully.',
            ], 200);
        } catch (\DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'COMMODITY_SUBCATEGORY_IN_USE',
            ], 409);
        }
    }

    /**
     * Bulk soft delete multiple commodity subcategories atomically in a single transaction.
     */
    public function bulkDestroy(
        BulkDeleteCommoditySubcategoryRequest $request,
        CommoditySubcategoryService $service
    ): JsonResponse {
        $result = $service->bulkDeleteCommoditySubcategories($request->validated('ids'));

        if (! empty($result['blocked_ids'])) {
            return response()->json([
                'status' => false,
                'message' => 'Some commodity subcategories cannot be deleted because they have varieties assigned.',
                'data' => [
                    'blocked_ids' => $result['blocked_ids'],
                ],
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Commodity subcategories deleted successfully.',
            'data' => [
                'deleted_count' => $result['deleted_count'],
            ],
        ], 200);
    }

    /**
     * Export commodity subcategories to CSV.
     */
    public function export(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $subcategories = CommoditySubcategory::with(['commodity', 'media'])->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=commodity_subcategories.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['ID', 'Commodity ID', 'Commodity Name', 'Image URL', 'Name', 'Slug', 'Description', 'Sort Order', 'Status', 'Created At'];

        $callback = function() use($subcategories, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($subcategories as $subcategory) {
                fputcsv($file, [
                    $subcategory->id,
                    $subcategory->commodity_id,
                    $subcategory->commodity ? $subcategory->commodity->name : '',
                    $subcategory->media ? url($subcategory->media->image_url) : '',
                    $subcategory->name,
                    $subcategory->slug,
                    $subcategory->description,
                    $subcategory->sort_order,
                    $subcategory->status ? 'Active' : 'Inactive',
                    $subcategory->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import commodity subcategories from CSV.
     */
    public function import(\Illuminate\Http\Request $request, \App\Services\MediaService $mediaService): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('file');
        $fileHandle = fopen($file->getPathname(), 'r');
        $header = fgetcsv($fileHandle);
        $headerMap = array_flip(array_map('trim', array_map('strtolower', $header)));
        
        $importedCount = 0;
        $userId = $request->user()?->id;
        
        while (($row = fgetcsv($fileHandle)) !== false) {
            if (count($row) < 3) continue;

            $attachmentId = null; // No longer parsed from CSV
            $imageUrl = isset($headerMap['image url']) && !empty($row[$headerMap['image url']]) ? $row[$headerMap['image url']] : null;
            $commodityId = $row[$headerMap['commodity id']] ?? $row[1] ?? null;
            $name = $row[$headerMap['name']] ?? $row[3] ?? '';
            $slug = $row[$headerMap['slug']] ?? $row[4] ?? \Illuminate\Support\Str::slug($name);
            $desc = $row[$headerMap['description']] ?? $row[5] ?? null;
            $sortOrder = $row[$headerMap['sort order']] ?? $row[6] ?? 0;
            $status = $row[$headerMap['status']] ?? $row[7] ?? 'Active';
            
            $mediaId = null;
            if ($imageUrl) {
                try {
                    $media = $mediaService->resolve(null, null, $imageUrl, null);
                    if ($media) {
                        $mediaId = $media->id;
                    }
                } catch (\Exception $e) {
                    // Ignore media resolution errors during bulk import
                }
            }

            // Find existing subcategory if any to preserve media_id if empty in CSV
            $existingSub = CommoditySubcategory::where('slug', $slug)->first();
            if (!$imageUrl && $existingSub) {
                $mediaId = $existingSub->media_id;
            }

            if ($commodityId) {
                CommoditySubcategory::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'commodity_id' => $commodityId,
                        'name' => $name,
                        'description' => $desc,
                        'sort_order' => is_numeric($sortOrder) ? (int)$sortOrder : 0,
                        'status' => strtolower($status) === 'active' ? 1 : 0,
                        'media_id' => $mediaId,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                );
                $importedCount++;
            }
        }
        fclose($fileHandle);

        return response()->json([
            'status' => true,
            'message' => "Successfully imported {$importedCount} commodity subcategories.",
        ], 200);
    }
}
