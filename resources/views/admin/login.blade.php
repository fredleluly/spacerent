<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin - SpaceRent</title>
    <meta name="robots" content="noindex,nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-[100dvh] items-center justify-center bg-zinc-50 px-4">
    <div class="card w-full max-w-sm p-8 shadow-lift">
        <div class="flex items-center gap-2 text-lg font-semibold tracking-tight">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white"><i data-lucide="building-2" class="h-5 w-5"></i></span>
            SpaceRent Admin
        </div>
        <h1 class="mt-6 text-xl font-semibold">Masuk ke dashboard</h1>
        <form method="POST" action="{{ route('admin.login') }}" class="mt-5 space-y-4" data-once>
            @csrf
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="input" @error('email') aria-invalid="true" @enderror>
                @error('email')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="label">Kata sandi</label>
                <input id="password" type="password" name="password" required class="input">
            </div>
            <label class="flex items-center gap-2 text-sm text-zinc-700"><input type="checkbox" name="remember" class="h-4 w-4 rounded accent-brand-600"> Ingat saya</label>
            <button type="submit" class="btn btn-primary w-full" data-loading="Memproses...">Masuk</button>
        </form>
        <a href="{{ route('home') }}" class="mt-5 block text-center text-xs text-zinc-600 hover:text-zinc-900">&larr; Kembali ke situs</a>
    </div>
</body>
</html>
