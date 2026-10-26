<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin - SpaceRent</title>
    <meta name="robots" content="noindex,nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'] }, colors: { brand: { 50: '#eff8ff', 500: '#0b7bc1', 600: '#0369a1', 700: '#075985', 900: '#0b2a43' } } } } };</script>
    <script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-brand-900 px-4 font-sans antialiased">
    <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-2xl">
        <div class="flex items-center gap-2.5">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white"><i data-lucide="building" class="h-5 w-5"></i></span>
            <span class="text-lg font-extrabold text-brand-900">Space<span class="text-brand-600">Rent</span> Admin</span>
        </div>
        <h1 class="mt-6 text-xl font-extrabold">Masuk ke Dashboard</h1>
        <form method="POST" action="{{ route('admin.login') }}" class="mt-5 space-y-4">
            @csrf
            @error('email')<div class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">{{ $message }}</div>@enderror
            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">Email
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm normal-case text-slate-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </label>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">Password
                <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="rounded border-slate-300"> Ingat saya</label>
            <button class="w-full rounded-lg bg-brand-600 py-3 text-sm font-bold text-white hover:bg-brand-700">Masuk</button>
        </form>
        <a href="{{ route('home') }}" class="mt-5 block text-center text-xs text-slate-400 hover:text-brand-600">&larr; Kembali ke website</a>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
