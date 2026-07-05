<footer class="ftco-footer ftco-section img" style="background-image: none;">
    <div class="overlay"></div>
    <div class="container">
        <div class="row mb-5">
            <div class="col-lg-4 col-md-6 mb-5 mb-md-5 ftco-animate">
                <div class="ftco-footer-widget mb-4">
                    <h1 class="logo">ATLAS</h1>
                    <p class="mt-4" style="line-height: 1.9;">
                        A connected fitness ecosystem for gym discovery, operational control, trainer visibility, and stronger member journeys.
                    </p>
                </div>
            </div>
            <div class="col-lg col-md-6 mb-5 mb-md-5 ftco-animate">
                <div class="ftco-footer-widget mb-4">
                    <h2 class="location">Explore</h2>
                    <ul class="list-unstyled">
                        <li><a href="{{ route('public.home') }}">Home</a></li>
                        <li><a href="{{ route('public.gyms.index') }}">Find Gyms</a></li>
                        <li><a href="{{ route('public.pricing') }}">Pricing</a></li>
                        <li><a href="{{ route('public.about') }}">About</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg col-md-6 mb-5 mb-md-5 ftco-animate">
                <div class="ftco-footer-widget mb-4">
                    <h2 class="location">Partners</h2>
                    <ul class="list-unstyled">
                        <li><a href="{{ route('public.for-gyms') }}">For Gyms</a></li>
                        <li><a href="{{ route('public.for-trainers') }}">For Trainers</a></li>
                        <li><a href="{{ route('public.contact') }}">Contact</a></li>
                        <li><a href="{{ route('web.gym.login') }}">Gym Login</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-5 mb-md-5 ftco-animate">
                <div class="ftco-footer-widget mb-4">
                    <h2 class="location">Platform Focus</h2>
                    <p style="line-height: 1.9;">Discovery, memberships, attendance, leads, and trainer-linked operations in one place.</p>
                    <ul class="ftco-footer-social list-unstyled float-md-left float-lft mt-4">
                        <li class="ftco-animate"><a href="{{ route('public.gyms.index') }}"><span class="icon-map-marker"></span></a></li>
                        <li class="ftco-animate"><a href="{{ route('public.for-gyms') }}"><span class="icon-briefcase"></span></a></li>
                        <li class="ftco-animate"><a href="{{ route('public.contact') }}"><span class="icon-paper-plane"></span></a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12 text-center">
                <p>
                    © {{ date('Y') }} ATLAS. Built by Techybugs.
                    <span class="mx-2">|</span>
                    <a href="{{ route('public.privacy-policy') }}">Privacy Policy</a>
                    <span class="mx-2">|</span>
                    <a href="{{ route('public.terms') }}">Terms</a>
                </p>
            </div>
        </div>
    </div>
</footer>
