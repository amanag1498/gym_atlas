<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Public\StoreGymSelfEnrollmentRequest;
use App\Models\FitnessGoal;
use App\Services\Members\GymSelfEnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GymSelfEnrollmentController extends Controller
{
    public function __construct(private readonly GymSelfEnrollmentService $service) {}

    public function show(string $token): View
    {
        $link = $this->service->resolveActiveLink($token);

        return view('public.self-enrollment.show', [
            'link' => $link,
            'gym' => $link->gym,
            'branches' => $link->branch_id === null
                ? $link->gym->branches()->where('is_active', true)->where('status', 'active')->orderBy('name')->get()
                : collect([$link->branch]),
            'fitnessGoals' => FitnessGoal::query()->active()->ordered()->get(),
            'firebaseConfig' => $this->firebaseWebConfig(),
        ]);
    }

    public function store(StoreGymSelfEnrollmentRequest $request, string $token): RedirectResponse
    {
        $link = $this->service->resolveActiveLink($token);
        $submission = $this->service->enrollNew($link, $request->validated(), $request);

        return redirect()->route('public.self-enrollment.success', [
            'token' => $link->token,
            'submission' => $submission->id,
        ]);
    }

    public function completed(Request $request, string $token): View
    {
        $link = $this->service->resolveActiveLink($token);
        $submission = $link->submissions()->whereKey($request->integer('submission'))->firstOrFail();

        return view('public.self-enrollment.success', compact('link', 'submission'));
    }

    /** @return array<string, string>|null */
    private function firebaseWebConfig(): ?array
    {
        $projectId = (string) config('services.firebase.project_id');
        $apiKey = (string) config('services.firebase.web_api_key');
        $appId = (string) config('services.firebase.web_app_id');
        $messagingSenderId = (string) config('services.firebase.messaging_sender_id');
        if ($projectId === '' || $apiKey === '' || $appId === '' || $messagingSenderId === '') {
            return null;
        }

        $authDomain = (string) config('services.firebase.auth_domain');
        $storageBucket = (string) config('services.firebase.storage_bucket');

        return [
            'apiKey' => $apiKey,
            'authDomain' => $authDomain !== '' ? $authDomain : $projectId.'.firebaseapp.com',
            'projectId' => $projectId,
            'storageBucket' => $storageBucket !== '' ? $storageBucket : $projectId.'.appspot.com',
            'messagingSenderId' => $messagingSenderId,
            'appId' => $appId,
        ];
    }
}
