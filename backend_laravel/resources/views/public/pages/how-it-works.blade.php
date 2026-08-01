@php
    $steps = [
        ['number' => '01', 'role' => 'Start', 'title' => 'Choose an independent or connected path', 'copy' => 'Members may begin with personal workouts, diet and progress. Trainers may create an independent account and complete verification before adding members or managing plans. A gym connection can be added when it is relevant.', 'image' => null, 'alt' => null],
        ['number' => '02', 'role' => 'Gym', 'title' => 'Receive structured intent', 'copy' => 'Trial and contact requests arrive inside the gym workflow, where operators can review, assign, update, complete, and convert them.', 'image' => null, 'alt' => null],
        ['number' => '03', 'role' => 'Operations', 'title' => 'Continue the member lifecycle', 'copy' => 'Membership, collections, attendance, branch, trainer, reminder, and reporting records stay connected after conversion.', 'image' => null, 'alt' => null],
        ['number' => '04', 'role' => 'Trainer', 'title' => 'Coach with member context', 'copy' => 'Assigned members, plans, exercises, diet guidance, notes, tasks, attendance, and progress become part of one coaching view.', 'image' => '/images/product/trainer/clients-720.webp', 'alt' => 'Atlas Trainer App assigned clients screen.'],
        ['number' => '05', 'role' => 'Member', 'title' => 'Act, log, and understand progress', 'copy' => 'Members follow workouts and diets, log sessions and meals, review history, track progress, and stay connected through notifications and chat.', 'image' => '/images/product/member/workouts-720.webp', 'alt' => 'Atlas Member App workout selection and tracking screen.'],
    ];
@endphp

<x-public.layouts.app page-title="How Atlas Works" page-description="See how members and verified trainers can start independently, or follow the connected Gym Atlas journey through discovery, operations, coaching and progress.">
    <div class="atlas-journey-story">
    <section class="atlas-journey-hero relative overflow-hidden bg-slate-950 pb-20 pt-36 text-white sm:pb-24 sm:pt-40">
        <div class="absolute inset-0 opacity-35" style="background-image: url('{{ asset('images/product/member/feature-network-1024.webp') }}'); background-position: center; background-size: cover;" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-slate-950/70" aria-hidden="true"></div>
        <div class="public-container relative text-center">
            <p class="public-eyebrow">How Atlas works</p>
            <h1 class="mx-auto mt-6 max-w-5xl text-4xl font-semibold leading-[1.02] tracking-[-0.045em] text-white sm:text-6xl lg:text-7xl">One journey, shared by every side of the gym relationship.</h1>
            <p class="mx-auto mt-6 max-w-3xl text-base leading-8 text-slate-300 sm:text-lg">Members can start independently, and trainers can coach independently after verification. When a gym relationship exists, discovery creates intent, operations turn intent into membership, coaching creates progress, and members keep the journey moving.</p>
        </div>
    </section>

    <section class="atlas-journey-section public-section bg-white">
        <div class="public-container">
            <div class="atlas-journey-line space-y-8">
                @foreach ($steps as $step)
                    <article class="atlas-journey-step public-surface-premium grid overflow-hidden lg:grid-cols-2 lg:items-center">
                        <div class="p-7 sm:p-10 {{ $loop->even ? 'lg:order-2' : '' }}">
                            <div class="flex items-center gap-3">
                                <span class="atlas-journey-number text-xs font-bold tracking-[0.2em] text-brand-500">{{ $step['number'] }}</span>
                                <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">{{ $step['role'] }}</span>
                            </div>
                            <h2 class="mt-5 text-3xl font-semibold tracking-tight text-slate-950">{{ $step['title'] }}</h2>
                            <p class="mt-4 text-base leading-8 text-slate-600">{{ $step['copy'] }}</p>
                        </div>
                        <div class="min-h-72 bg-[linear-gradient(145deg,#eef3ff,#ffffff)] p-6 {{ $loop->even ? 'lg:order-1' : '' }}">
                            @if ($step['image'])
                                <img src="{{ asset(ltrim($step['image'], '/')) }}" alt="{{ $step['alt'] }}" width="720" height="1280" loading="lazy" decoding="async" class="mx-auto max-h-[30rem] w-auto rounded-[1.5rem] border border-slate-200 shadow-2xl shadow-slate-950/10">
                            @else
                                <div class="atlas-journey-connection flex h-full min-h-64 items-center justify-center rounded-[1.5rem] border border-brand-100 bg-white/70 p-8 text-center">
                                    <div>
                                        <span class="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-500 text-2xl font-semibold text-white">{{ $step['number'] }}</span>
                                        <p class="mt-5 max-w-sm text-sm leading-7 text-slate-600">This is an operational connection, explained with verified workflow copy instead of an invented product screenshot.</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="public-section bg-slate-50">
        <div class="public-container">
            <x-public.cta-section
                eyebrow="Choose your starting point"
                title="Explore the part of Atlas that matches your role."
                copy="Members can begin independently, trainers can review the verification path or gym-connected workspace, and gym operators can explore the complete management layer."
                primary-label="Explore the product"
                :primary-href="route('public.product')"
                secondary-label="Find gyms"
                :secondary-href="route('public.gyms.index')"
            />
        </div>
    </section>
    </div>
</x-public.layouts.app>
