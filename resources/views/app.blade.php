<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="author" content="Rochmad Nurul Fahmi" />
    <title>Tensura</title>

    @viteReactRefresh

    @vite([ 'resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx" ])
    <x-inertia::head>
        <title>{{ config('app.name', 'Tensura') }}</title>
    </x-inertia::head>
</head>

<body class="bg-gray-50 font-sans text-gray-900 antialiased">
    <x-inertia::app id="tensura-root" />
</body>
</html>
