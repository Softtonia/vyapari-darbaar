<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\UserKycDocument;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserKycController extends Controller
{
    /**
     * Get user's KYC documents and overall status.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $documents = $user->kycDocuments()->get();

        return response()->json([
            'status' => true,
            'message' => 'KYC details retrieved successfully.',
            'data' => [
                'kyc_status' => $user->kyc_status,
                'documents' => $documents,
            ]
        ]);
    }

    /**
     * Upload or replace a KYC document.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|in:Aadhaar Card,PAN Card,Passport Photo',
            'file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Cannot upload if already verified or under review
        if (in_array($user->kyc_status, ['verified', 'under_review'])) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot modify documents while KYC is ' . str_replace('_', ' ', $user->kyc_status) . '.'
            ], 403);
        }

        $type = $request->input('document_type');
        
        // Find existing document of this type
        $existing = $user->kycDocuments()->where('document_type', $type)->first();
        
        if ($existing && $existing->status === 'verified') {
            return response()->json([
                'status' => false,
                'message' => 'This document is already verified and cannot be replaced.'
            ], 403);
        }

        $file = $request->file('file');
        $path = $file->store('kyc_documents', 'public'); // Adjust storage disk if needed for security

        if ($existing) {
            // Delete old file
            if (Storage::disk('public')->exists($existing->file_path)) {
                Storage::disk('public')->delete($existing->file_path);
            }
            $existing->update([
                'file_path' => $path,
                'status' => 'pending',
                'rejection_reason' => null
            ]);
            $document = $existing;
        } else {
            $document = $user->kycDocuments()->create([
                'document_type' => $type,
                'file_path' => $path,
                'status' => 'pending',
            ]);
        }

        // Move overall status to unverified/pending if it was rejected
        if ($user->kyc_status === 'rejected') {
            $user->update(['kyc_status' => 'pending']);
        } else if ($user->kyc_status === 'unverified') {
            $user->update(['kyc_status' => 'pending']);
        }

        return response()->json([
            'status' => true,
            'message' => 'Document uploaded successfully.',
            'data' => $document
        ], 201);
    }

    /**
     * Submit KYC for review.
     */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();

        if (in_array($user->kyc_status, ['verified', 'under_review'])) {
            return response()->json([
                'status' => false,
                'message' => 'KYC is already ' . str_replace('_', ' ', $user->kyc_status) . '.'
            ], 400);
        }

        $documents = $user->kycDocuments()->get();
        $types = $documents->pluck('document_type')->toArray();
        
        // Example logic: Aadhaar and PAN are required
        $required = ['Aadhaar Card', 'PAN Card'];
        $missing = array_diff($required, $types);
        
        if (!empty($missing)) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot submit KYC. Missing required documents: ' . implode(', ', $missing),
            ], 422);
        }

        // Check if any document is rejected
        if ($documents->where('status', 'rejected')->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Please re-upload rejected documents before submitting.'
            ], 422);
        }

        $user->update(['kyc_status' => 'under_review']);

        return response()->json([
            'status' => true,
            'message' => 'KYC submitted successfully for review.',
            'data' => ['kyc_status' => $user->kyc_status]
        ]);
    }

    /**
     * Delete a document.
     */
    public function destroy($id, Request $request): JsonResponse
    {
        $user = $request->user();
        $document = $user->kycDocuments()->find($id);

        if (!$document) {
            return response()->json(['status' => false, 'message' => 'Document not found.'], 404);
        }

        if ($document->status === 'verified' || in_array($user->kyc_status, ['verified', 'under_review'])) {
            return response()->json(['status' => false, 'message' => 'Cannot delete document at this stage.'], 403);
        }

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'status' => true,
            'message' => 'Document deleted successfully.'
        ]);
    }
}
