@extends('layouts.app')

@section('title', 'Hubungkan Telegram')

@section('content')
@section('account_content')
    <h1 class="text-2xl font-extrabold tracking-tight">Hubungkan Telegram</h1>
    <p class="text-sm text-slate-500 mt-1">Link akun website ke bot Telegram untuk order langsung dari Telegram.</p>

    <div class="mt-6 rounded-2xl bg-white border border-slate-200 p-5 md:p-6 space-y-5">
        @if ($user->telegram_chat_id)
            {{-- Sudah linked --}}
            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4">
                <div class="flex items-start gap-3">
                    <span class="text-2xl">✅</span>
                    <div class="flex-1">
                        <p class="font-bold text-emerald-800">Akun terhubung</p>
                        <p class="text-sm text-emerald-700 mt-1">
                            Chat ID: <code class="bg-white px-1 rounded">{{ $user->telegram_chat_id }}</code>
                            @if ($user->telegram_username)
                                · @{{ $user->telegram_username }}
                            @endif
                        </p>
                        <p class="text-xs text-emerald-600 mt-2">
                            Saat order PAID, kredensial akun premium akan otomatis dikirim ke chat Telegram kamu.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <a href="https://t.me/{{ $botUsername }}" target="_blank"
                   class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-sky-500 hover:bg-sky-600 text-white font-semibold px-5 py-3">
                    💬 Buka Bot
                </a>
                <form method="POST" action="{{ route('account.telegram.unlink') }}" class="flex-1">
                    @csrf
                    <button type="submit"
                            onclick="return confirm('Yakin mau unlink akun Telegram?')"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 font-semibold px-5 py-3 border border-rose-200">
                        Unlink Akun
                    </button>
                </form>
            </div>
        @else
            {{-- Belum linked --}}
            <div class="rounded-xl bg-sky-50 border border-sky-200 p-4">
                <p class="font-bold text-sky-800">📱 Bot Telegram: @{{ $botUsername }}</p>
                <p class="text-sm text-sky-700 mt-1">Hubungkan akun untuk order via bot dan auto-receive kredensial.</p>
            </div>

            @if ($token)
                <div class="rounded-xl bg-amber-50 border border-amber-200 p-4">
                    <p class="text-sm text-amber-800 mb-2">Token aktif (berlaku 15 menit):</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 text-2xl font-bold tracking-widest text-amber-900 bg-white px-4 py-3 rounded-lg border-2 border-dashed border-amber-300 text-center">
                            {{ $token->token }}
                        </code>
                        <button type="button"
                                onclick="navigator.clipboard.writeText('{{ $token->token }}'); this.textContent='✓'"
                                class="px-3 py-3 rounded-lg bg-white border border-amber-300 text-amber-700 hover:bg-amber-100">
                            📋
                        </button>
                    </div>
                    <p class="text-xs text-amber-700 mt-2">
                        Berlaku sampai {{ $token->expires_at->format('H:i') }} ({{ $token->expires_at->diffForHumans() }}).
                    </p>
                </div>

                <div class="rounded-xl bg-slate-50 border border-slate-200 p-4">
                    <p class="font-bold mb-3">📋 Cara link akun:</p>
                    <ol class="list-decimal list-inside text-sm text-slate-700 space-y-2">
                        <li>Buka bot di Telegram:
                            <a href="https://t.me/{{ $botUsername }}?start={{ $token->token }}" target="_blank"
                               class="text-sky-600 font-semibold underline">
                                @{{ $botUsername }}
                            </a>
                        </li>
                        <li>Klik tombol <b>START</b> atau ketik:
                            <code class="bg-white px-2 py-1 rounded">/start {{ $token->token }}</code>
                        </li>
                        <li>Akun otomatis ter-link 🎉</li>
                    </ol>

                    <a href="https://t.me/{{ $botUsername }}?start={{ $token->token }}" target="_blank"
                       class="mt-4 inline-flex items-center justify-center gap-2 rounded-xl bg-sky-500 hover:bg-sky-600 text-white font-semibold px-5 py-3 w-full">
                        🔗 Buka Telegram & Auto-Link
                    </a>
                </div>
            @endif

            <form method="POST" action="{{ route('account.telegram.generate') }}">
                @csrf
                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold px-5 py-3">
                    {{ $token ? '🔄 Generate Token Baru' : '✨ Generate Token' }}
                </button>
            </form>
        @endif
    </div>
@endsection

@include('account._layout')
@endsection
