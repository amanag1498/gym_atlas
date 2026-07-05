<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $type = trim((string) $request->string('type'));
        $status = trim((string) $request->string('status'));

        $enquiries = ContactSubmission::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('message', 'like', '%'.$search.'%');
                });
            })
            ->when($type !== '', fn ($query) => $query->where('inquiry_type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $visibleItems = $enquiries->getCollection();

        return view('web.admin.enquiries.index', [
            'pageTitle' => 'Frontend Enquiries',
            'breadcrumbs' => ['Platform', 'Frontend Enquiries'],
            'enquiries' => $enquiries,
            'inquiryTypes' => ContactSubmission::query()
                ->select('inquiry_type')
                ->distinct()
                ->orderBy('inquiry_type')
                ->pluck('inquiry_type')
                ->all(),
            'statusOptions' => ['new', 'in_progress', 'resolved', 'spam'],
            'summary' => [
                'total' => ContactSubmission::query()->count(),
                'new' => ContactSubmission::query()->where('status', 'new')->count(),
                'gym' => ContactSubmission::query()->where('inquiry_type', 'gym')->count(),
                'trainer' => ContactSubmission::query()->where('inquiry_type', 'trainer')->count(),
                'visible' => $visibleItems->count(),
                'resolved_visible' => $visibleItems->where('status', 'resolved')->count(),
            ],
        ]);
    }

    public function updateStatus(Request $request, ContactSubmission $enquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:new,in_progress,resolved,spam'],
        ]);

        $enquiry->update([
            'status' => $validated['status'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Enquiry status updated.');
    }
}
