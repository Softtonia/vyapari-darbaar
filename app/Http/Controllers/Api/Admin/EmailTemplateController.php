<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\EmailTemplate\BulkDeleteEmailTemplatesAction;
use App\Actions\Admin\EmailTemplate\CreateEmailTemplateAction;
use App\Actions\Admin\EmailTemplate\DeleteEmailTemplateAction;
use App\Actions\Admin\EmailTemplate\PreviewEmailTemplateAction;
use App\Actions\Admin\EmailTemplate\UpdateEmailTemplateAction;
use App\Actions\Admin\EmailTemplate\UpdateEmailTemplateStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmailTemplate\BulkDeleteEmailTemplatesRequest;
use App\Http\Requests\Admin\EmailTemplate\PreviewEmailTemplateRequest;
use App\Http\Requests\Admin\EmailTemplate\StoreEmailTemplateRequest;
use App\Http\Requests\Admin\EmailTemplate\UpdateEmailTemplateRequest;
use App\Http\Requests\Admin\EmailTemplate\UpdateEmailTemplateStatusRequest;
use App\Http\Resources\EmailTemplateListResource;
use App\Http\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    /**
     * Display a paginated listing of email templates (excluding body).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $query = EmailTemplate::query()
            ->select(['id', 'name', 'key', 'subject', 'is_active', 'created_at', 'updated_at']);

        // Search filter
        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('is_active') && $request->input('is_active') !== null && $request->input('is_active') !== '') {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $paginator = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Email templates retrieved successfully.',
            'data' => [
                'current_page' => $paginator->currentPage(),
                'data' => EmailTemplateListResource::collection($paginator->items()),
                'first_page_url' => $paginator->url(1),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'last_page_url' => $paginator->url($paginator->lastPage()),
                'links' => $paginator->linkCollection()->toArray(),
                'next_page_url' => $paginator->nextPageUrl(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    /**
     * Store a newly created email template.
     */
    public function store(StoreEmailTemplateRequest $request, CreateEmailTemplateAction $action): JsonResponse
    {
        $template = $action->execute($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Email template created successfully.',
            'data' => new EmailTemplateResource($template),
        ], 201);
    }

    /**
     * Display the specified email template by ID.
     */
    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Email template retrieved successfully.',
            'data' => new EmailTemplateResource($emailTemplate),
        ], 200);
    }

    /**
     * Display the specified email template by unique key.
     */
    public function byKey(string $key): JsonResponse
    {
        $template = EmailTemplate::query()
            ->where('key', $key)
            ->first();

        if (! $template) {
            return response()->json([
                'status' => false,
                'message' => 'Email template not found.',
                'error' => 'Not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Email template retrieved successfully.',
            'data' => new EmailTemplateResource($template),
        ], 200);
    }

    /**
     * Update the specified email template.
     */
    public function update(
        UpdateEmailTemplateRequest $request,
        EmailTemplate $emailTemplate,
        UpdateEmailTemplateAction $action
    ): JsonResponse {
        $template = $action->execute($emailTemplate, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Email template updated successfully.',
            'data' => new EmailTemplateResource($template),
        ], 200);
    }

    /**
     * Update the active status of the specified email template.
     */
    public function updateStatus(
        UpdateEmailTemplateStatusRequest $request,
        EmailTemplate $emailTemplate,
        UpdateEmailTemplateStatusAction $action
    ): JsonResponse {
        $template = $action->execute($emailTemplate, (bool) $request->input('is_active'));

        return response()->json([
            'status' => true,
            'message' => 'Email template status updated successfully.',
            'data' => new EmailTemplateResource($template),
        ], 200);
    }

    /**
     * Preview an email template with sample placeholder values before saving.
     */
    public function preview(PreviewEmailTemplateRequest $request, PreviewEmailTemplateAction $action): JsonResponse
    {
        $rendered = $action->execute(
            (string) $request->input('key'),
            (string) $request->input('subject'),
            (string) $request->input('body')
        );

        return response()->json([
            'status' => true,
            'message' => 'Preview generated successfully.',
            'data' => $rendered,
        ], 200);
    }

    /**
     * Delete the specified email template.
     */
    public function destroy(EmailTemplate $emailTemplate, DeleteEmailTemplateAction $action): JsonResponse
    {
        $action->execute($emailTemplate);

        return response()->json([
            'status' => true,
            'message' => 'Email template deleted successfully.',
        ], 200);
    }

    /**
     * Bulk delete multiple email templates.
     */
    public function bulkDestroy(
        BulkDeleteEmailTemplatesRequest $request,
        BulkDeleteEmailTemplatesAction $action
    ): JsonResponse {
        $deletedCount = $action->execute($request->input('ids'));

        return response()->json([
            'status' => true,
            'message' => "{$deletedCount} email templates deleted successfully.",
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ], 200);
    }
}
