@extends('layouts.app')

@section('title', 'Дашборд — '.config('app.name'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-12">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">Дашборд</h1>
            <p class="mt-1 text-sm text-gray-500">{{ trim(auth()->user()->first_name.' '.auth()->user()->last_name) ?: auth()->user()->phone }}</p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Выйти
            </button>
        </form>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <a href="{{ $pulseUrl }}"
            class="group rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:ring-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 group-hover:text-black">Pulse</h2>
            <p class="mt-1 text-sm text-gray-500">Мониторинг производительности и состояния приложения.</p>
        </a>

        <a href="{{ $docsUrl }}"
            class="group rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200 transition hover:ring-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 group-hover:text-black">API-документация</h2>
            <p class="mt-1 text-sm text-gray-500">Интерактивная документация API (Scramble).</p>
        </a>
    </div>
</div>
@endsection
