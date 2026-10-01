<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommodityCategory\BulkDeleteCommodityCategoryRequest;
use App\Http\Requests\Admin\CommodityCategory\BulkUpdateCommodityCategoryStatusRequest;
use App\Http\Requests\Admin\CommodityCategory\ListCommodityCategoryRequest;
use App\Http\Requests\Admin\CommodityCategory\StoreCommodityCategoryRequest;
use App\Http\Requests\Admin\CommodityCategory\UpdateCommodityCategoryRequest;
use App\Http\Requests\Admin\CommodityCategory\UpdateCommodityCategoryStatusRequest;
use App\Http\Resources\CommodityCategoryResource;
use App\Models\CommodityCategory;
use App\Services\CommodityCategoryService;
use DomainException;
use Illuminate\Http\JsonResponse;

class CommodityCategoryController extends Controller
{
    /**
     * Display a paginated listing of commodity categories.
     */
    public function index(ListCommodityCategoryRequest $request, CommodityCategoryService $service): JsonResponse
    {
        $paginator = $service->listCategories($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Commodity categories fetched successfully.',
            'data' => [
                'items' => CommodityCategoryResource::collection($paginator->items()),
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
     * Get lightweight, cached list of active commodity categories for dropdown options.
     */
    public function options(CommodityCategoryService $service): JsonResponse
    {
        $options = $service->getOptions();

        return response()->json([
            'status' => true,
            'message' => 'Commodity category options retrieved successfully.',
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created commodity category.
     */
    public function store(StoreCommodityCategoryRequest $request, CommodityCategoryService $service, \App\Services\MediaService $mediaService): JsonResponse
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

        $category = $service->createCategory(
            $data,
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity category created successfully.',
            'data' => new CommodityCategoryResource($category),
        ], 201);
    }

    /**
     * Display the specified commodity category detail.
     */
    public function show(CommodityCategory $commodityCategory): JsonResponse
    {
        $commodityCategory->load(['creator', 'updater']);

        return response()->json([
            'status' => true,
            'message' => 'Commodity category retrieved successfully.',
            'data' => new CommodityCategoryResource($commodityCategory),
        ], 200);
    }

    /**
     * Update the specified commodity category.
     */
    public function update(
        UpdateCommodityCategoryRequest $request,
        CommodityCategory $commodityCategory,
        CommodityCategoryService $service,
        \App\Services\MediaService $mediaService
    ): JsonResponse {
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

        $category = $service->updateCategory(
            $commodityCategory,
            $data,
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity category updated successfully.',
            'data' => new CommodityCategoryResource($category),
        ], 200);
    }

    /**
     * Update the active/inactive status of a commodity category.
     */
    public function updateStatus(
        UpdateCommodityCategoryStatusRequest $request,
        CommodityCategory $commodityCategory,
        CommodityCategoryService $service
    ): JsonResponse {
        $category = $service->updateStatus(
            $commodityCategory,
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity category status updated successfully.',
            'data' => new CommodityCategoryResource($category),
        ], 200);
    }

    /**
     * Bulk update status for multiple commodity categories.
     */
    public function bulkStatus(
        BulkUpdateCommodityCategoryStatusRequest $request,
        CommodityCategoryService $service
    ): JsonResponse {
        $updatedCount = $service->bulkUpdateStatus(
            $request->validated('ids'),
            $request->validated('status'),
            $request->user()?->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Commodity categories status updated successfully.',
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ], 200);
    }

    /**
     * Soft delete a single commodity category safely.
     */
    public function destroy(CommodityCategory $commodityCategory, CommodityCategoryService $service): JsonResponse
    {
        try {
            $service->deleteCategory($commodityCategory);

            return response()->json([
                'status' => true,
                'message' => 'Commodity category deleted successfully.',
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error' => 'CATEGORY_IN_USE',
            ], 409);
        }
    }

    /**
     * Bulk soft delete multiple commodity categories atomically in a single transaction.
     */
    public function bulkDestroy(
        BulkDeleteCommodityCategoryRequest $request,
        CommodityCategoryService $service
    ): JsonResponse {
        $result = $service->bulkDeleteCategories($request->validated('ids'));

        if (! empty($result['blocked_ids'])) {
            return response()->json([
                'status' => false,
                'message' => 'Some commodity categories cannot be deleted because they are in use.',
                'data' => [
                    'blocked_ids' => $result['blocked_ids'],
                ],
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Commodity categories deleted successfully.',
            'data' => [
                'deleted_count' => $result['deleted_count'],
            ],
        ], 200);
    }

    /**
     * Export commodity categories to CSV.
     */
    public function export(\Illuminate\Http\Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $categories = CommodityCategory::with('media')->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=commodity_categories.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['ID', 'Attachment ID', 'Image URL', 'Name', 'Slug', 'Description', 'Sort Order', 'Status', 'Created At'];

        $callback = function() use($categories, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($categories as $category) {
                fputcsv($file, [
                    $category->id,
                    $category->media ? $category->media->attachment_id : '',
                    $category->media ? url($category->media->image_url) : '',
                    $category->name,
                    $category->slug,
                    $category->description,
                    $category->sort_order,
                    $category->status ? 'Active' : 'Inactive',
                    $category->created_at,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import commodity categories from CSV.
     */
    public function import(\Illuminate\Http\Request $request, \App\Services\MediaService $mediaService): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('file');
        $fileHandle = fopen($file->getPathname(), 'r');
        
        // Ensure to read header to figure out indices dynamically, or just map them. 
        // For simplicity, we assume strict column order based on export:
        // ['ID', 'Attachment ID', 'Image URL', 'Name', 'Slug', 'Description', 'Sort Order', 'Status', 'Created At']
        $header = fgetcsv($fileHandle);
        $headerMap = array_flip(array_map('trim', array_map('strtolower', $header)));
        
        $importedCount = 0;
        $userId = $request->user()?->id;
        
        while (($row = fgetcsv($fileHandle)) !== false) {
            if (count($row) < 2) continue;
            
            $attachmentId = isset($headerMap['attachment id']) && !empty($row[$headerMap['attachment id']]) ? (int)$row[$headerMap['attachment id']] : null;
            $imageUrl = isset($headerMap['image url']) && !empty($row[$headerMap['image url']]) ? $row[$headerMap['image url']] : null;
            $name = $row[$headerMap['name']] ?? $row[1] ?? '';
            $slug = $row[$headerMap['slug']] ?? $row[2] ?? \Illuminate\Support\Str::slug($name);
            $desc = $row[$headerMap['description']] ?? $row[3] ?? null;
            $sortOrder = $row[$headerMap['sort order']] ?? $row[4] ?? 0;
            $status = $row[$headerMap['status']] ?? $row[5] ?? 'Active';
            
            $mediaId = null;
            if ($attachmentId || $imageUrl) {
                try {
                    $media = $mediaService->resolve(null, null, $imageUrl, $attachmentId);
                    if ($media) {
                        $mediaId = $media->id;
                    }
                } catch (\Exception $e) {
                    // Ignore media resolution errors during bulk import
                }
            }

            CommodityCategory::updateOrCreate(
                ['slug' => $slug],
                [
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
        fclose($fileHandle);

        return response()->json([
            'status' => true,
            'message' => "Successfully imported {$importedCount} commodity categories.",
        ], 200);
    }
}
