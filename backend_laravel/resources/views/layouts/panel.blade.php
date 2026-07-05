<x-admin.app-layout
    :page-title="$pageTitle ?? config('app.name')"
    :panel-context="$panelContext ?? []"
    :breadcrumbs="$breadcrumbs ?? []"
>
    @yield('content')
</x-admin.app-layout>
