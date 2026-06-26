@extends('layouts.app')

@section('title', 'Вход — '.config('app.name'))

@section('content')
<div class="flex min-h-full items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm">
        <div class="rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
            <h1 class="mb-1 text-center text-xl font-semibold text-gray-900">Панель администратора</h1>
            <p class="mb-6 text-center text-sm text-gray-500">Войдите, чтобы открыть Pulse и API-документацию.</p>

            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">Телефон</label>
                    <input id="phone" name="phone" type="text" inputmode="numeric" value="{{ old('phone') }}"
                        placeholder="998901234567" autofocus
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Пароль</label>
                    <input id="password" name="password" type="password" placeholder="••••••••"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900">
                </div>

                <button type="submit"
                    class="w-full rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-black">
                    Войти
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
