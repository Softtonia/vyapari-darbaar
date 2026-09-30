<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserKycDocument;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AdminUserKycController extends Controller
{
    /**
     * List users awaiting KYC review (or filter by status).
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->input('status', 'under_review');
        
        $users = User::with('kycDocuments')
            ->where('kyc_status', $status)
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'status' => true,
            'message' => 'Users retrieved successfully.',
            'data' => $users
        ]);
    }

    /**
     * View a user's KYC documents.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('kycDocuments');

        return response()->json([
            'status' => true,
            'message' => 'User KYC retrieved successfully.',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'kyc_status' => $user->kyc_status,
                'documents' => $user->kycDocuments
            ]
        ]);
    }

    /**
     * View an individual document.
     */
    public function showDocument($id): JsonResponse
    {
        $document = UserKycDocument::with('user:id,first_name,last_name,kyc_status')->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Document retrieved successfully.',
            'data' => $document
        ]);
    }

    /**
     * Update individual document status (Approve, Reject/Re-upload).
     */
    public function updateDocumentStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:verified,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $document = UserKycDocument::findOrFail($id);
        
        $document->update([
            'status' => $request->status,
            'rejection_reason' => $request->status === 'rejected' ? $request->rejection_reason : null
        ]);

        // If a document is rejected, the user's overall KYC status is set to rejected
        if ($request->status === 'rejected') {
            $document->user()->update(['kyc_status' => 'rejected']);
        }

        return response()->json([
            'status' => true,
            'message' => 'Document status updated successfully.',
            'data' => $document
        ]);
    }

    /**
     * Approve the user's overall KYC.
     */
    public function approveUserKyc(User $user): JsonResponse
    {
        // Must have required documents and all required must be verified
        $documents = $user->kycDocuments()->get();
        $types = $documents->pluck('document_type')->toArray();
        
        $required = ['Aadhaar Card', 'PAN Card'];
        $missing = array_diff($required, $types);
        
        if (!empty($missing)) {
            return response()->json([
                'status' => false,
                'message' => 'User is missing required documents: ' . implode(', ', $missing)
            ], 422);
        }

        // Check if all required documents are verified
        $unverifiedRequired = $documents->whereIn('document_type', $required)
                                        ->where('status', '!=', 'verified');

        if ($unverifiedRequired->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Not all required documents are verified yet.'
            ], 422);
        }

        $user->update(['kyc_status' => 'verified']);

        return response()->json([
            'status' => true,
            'message' => 'User KYC approved successfully.',
            'data' => ['kyc_status' => $user->kyc_status]
        ]);
    }
}
