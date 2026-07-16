<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck, CircleX, Send } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';

type CheckState = 'idle' | 'checking' | 'registered' | 'notfound';

const props = defineProps<{
    botUsername?: string | null;
}>();

// Deep link membuka chat bot langsung ke aksi /start (mendaftarkan user agar bisa
// menerima OTP). Telegram melarang bot memulai percakapan, jadi user WAJIB /start dulu.
const botStartUrl = computed(() =>
    props.botUsername ? `https://t.me/${props.botUsername}?start=login` : null,
);

// Format valid: Telegram ID (angka, min 5 digit) ATAU @username (5-32 huruf/angka/underscore).
const USERNAME_RE = /^@?[A-Za-z0-9_]{5,32}$/;
const ID_RE = /^\d{5,}$/;

const isValidFormat = (value: string) =>
    ID_RE.test(value) || USERNAME_RE.test(value);

defineOptions({
    inheritAttrs: false,
    layout: {
        title: 'Hubungkan Telegram',
        description:
            'Gunakan akun yang sudah memulai bot. Kami akan mengirim kode masuk melalui Telegram.',
    },
});

const identifier = ref('');
const code = ref('');
const sent = ref(false);
const busy = ref(false);
const message = ref('');
const error = ref('');

const checkState = ref<CheckState>('idle');
// Race guard: tiap perubahan input menaikkan counter; respons dari request lama diabaikan.
let checkSeq = 0;

const csrf = () =>
    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
        ? decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)![1])
        : '';
async function post(url: string, body: object) {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });

    return fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrf(),
        },
        body: JSON.stringify(body),
    });
}
async function checkIdentifier(value: string, seq: number) {
    try {
        const response = await post('/api/auth/check-identifier', {
            identifier: value,
        });
        const data = await response.json();

        // Abaikan bila input sudah berubah sejak request ini dikirim (race guard).
        if (seq !== checkSeq) {
            return;
        }

        if (!response.ok) {
            checkState.value = 'idle';

            return;
        }

        checkState.value = data.registered ? 'registered' : 'notfound';
    } catch {
        // Error jaringan: jangan bikin UI patah, cukup kembali netral.
        if (seq === checkSeq) {
            checkState.value = 'idle';
        }
    }
}

// Verifikasi real-time saat mengetik: validasi format dulu (murah), baru cek ke server.
watchDebounced(
    identifier,
    (value) => {
        const trimmed = value.trim();
        const seq = ++checkSeq;

        if (trimmed === '') {
            checkState.value = 'idle';

            return;
        }

        // Format belum lengkap/valid (mis. username < 5 karakter): diam dulu, jangan
        // tampilkan status "salah" saat user masih mengetik.
        if (!isValidFormat(trimmed)) {
            checkState.value = 'idle';

            return;
        }

        checkState.value = 'checking';
        void checkIdentifier(trimmed, seq);
    },
    { debounce: 500 },
);

async function requestCode() {
    busy.value = true;
    error.value = '';

    try {
        const response = await post('/api/auth/request-otp', {
            identifier: identifier.value.trim(),
        });
        const data = await response.json();

        if (!response.ok) {
            error.value =
                data.message || 'Kode tidak dapat dikirim. Coba lagi.';

            return;
        }

        sent.value = true;
        message.value = data.message;
        await nextTick();
        document.getElementById('telegram-code')?.focus();
    } catch {
        error.value =
            'DuitBot tidak dapat terhubung. Periksa koneksi lalu coba lagi.';
    } finally {
        busy.value = false;
    }
}
async function login() {
    busy.value = true;
    error.value = '';

    try {
        const response = await post('/api/auth/telegram-login', {
            identifier: identifier.value.trim(),
            code: code.value,
        });
        const data = await response.json();

        if (!response.ok) {
            error.value =
                data.message || 'Kode tidak valid. Periksa dan coba lagi.';

            return;
        }

        window.location.href = data.redirect;
    } catch {
        error.value =
            'DuitBot tidak dapat terhubung. Periksa koneksi lalu coba lagi.';
    } finally {
        busy.value = false;
    }
}
function changeAccount() {
    sent.value = false;
    code.value = '';
    message.value = '';
    error.value = '';
    checkState.value = 'idle';
    checkSeq++;
}
</script>

<template>
    <Head title="Hubungkan Telegram" />
    <form
        class="flex flex-col gap-6"
        aria-describedby="telegram-login-help"
        @submit.prevent="sent ? login() : requestCode()"
    >
        <div class="grid gap-2">
            <Label for="telegram-identifier">Telegram ID atau username</Label>
            <div class="relative">
                <Input
                    id="telegram-identifier"
                    v-model="identifier"
                    name="identifier"
                    type="text"
                    required
                    autocomplete="username"
                    :disabled="sent || busy"
                    :aria-invalid="Boolean(error)"
                    aria-describedby="telegram-login-help telegram-login-error"
                    placeholder="@username atau 123456789"
                    class="h-11 pr-10"
                />
                <span
                    v-if="!sent && checkState !== 'idle'"
                    class="pointer-events-none absolute inset-y-0 right-3 flex items-center"
                    aria-hidden="true"
                >
                    <Spinner
                        v-if="checkState === 'checking'"
                        class="h-5 w-5 text-muted-foreground"
                    />
                    <CircleCheck
                        v-else-if="checkState === 'registered'"
                        class="h-5 w-5 text-green-600 dark:text-green-500"
                    />
                    <CircleX
                        v-else-if="checkState === 'notfound'"
                        class="h-5 w-5 text-destructive"
                    />
                </span>
            </div>
            <p
                v-if="!sent && checkState === 'registered'"
                class="text-sm font-medium text-green-600 dark:text-green-500"
                role="status"
                aria-live="polite"
            >
                Akun Telegram ditemukan — kode akan dikirim ke sini.
            </p>
            <p
                v-else-if="!sent && checkState === 'notfound'"
                class="text-sm text-destructive"
                role="status"
                aria-live="polite"
            >
                Akun tidak ditemukan. Kalau ini pertama kali, buka bot dan tekan
                <b>Start</b> dulu lewat tombol di bawah.
            </p>
            <p
                v-else-if="!sent && checkState === 'checking'"
                class="text-sm text-muted-foreground"
                role="status"
                aria-live="polite"
            >
                Memeriksa…
            </p>
            <p
                v-else
                id="telegram-login-help"
                class="text-sm leading-relaxed text-muted-foreground"
            >
                Gunakan akun yang sudah mengirim <code>/start</code> ke bot.
            </p>
        </div>

        <div
            v-if="!sent && botStartUrl"
            class="rounded-lg border border-border bg-muted/40 p-4"
        >
            <p class="text-sm font-medium text-foreground">
                Pertama kali pakai DuitBot?
            </p>
            <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                Bot hanya bisa mengirim kode ke akun yang sudah memulainya. Buka
                bot, tekan <b>Start</b>, lalu kembali ke sini untuk minta kode.
            </p>
            <Button
                as-child
                variant="outline"
                size="lg"
                class="mt-3 h-11 w-full"
            >
                <a
                    :href="botStartUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <Send class="h-4 w-4" aria-hidden="true" />
                    Buka bot di Telegram
                </a>
            </Button>
        </div>

        <div v-if="sent" class="grid gap-2">
            <Label for="telegram-code">Kode 6 digit</Label>
            <Input
                id="telegram-code"
                v-model="code"
                name="code"
                type="text"
                required
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                pattern="[0-9]{6}"
                :disabled="busy"
                :aria-invalid="Boolean(error)"
                aria-describedby="telegram-login-message telegram-login-error"
                placeholder="000000"
                class="h-11 text-center text-lg tracking-[0.35em] tabular-nums"
            />
        </div>

        <p
            v-if="message"
            id="telegram-login-message"
            role="status"
            aria-live="polite"
            class="rounded-lg bg-primary/10 p-3 text-sm leading-relaxed text-foreground"
        >
            {{ message }}
        </p>

        <div
            id="telegram-login-error"
            role="alert"
            aria-live="assertive"
            class="min-h-5"
        >
            <InputError :message="error" />
        </div>

        <div class="grid gap-3">
            <Button
                type="submit"
                size="lg"
                class="h-11 w-full transition-colors motion-reduce:transition-none"
                :disabled="busy"
            >
                <Spinner v-if="busy" aria-hidden="true" />
                {{
                    busy
                        ? 'Memproses…'
                        : sent
                          ? 'Masuk ke dashboard'
                          : 'Kirim kode masuk'
                }}
            </Button>
            <Button
                v-if="sent"
                type="button"
                variant="ghost"
                size="lg"
                class="h-11 w-full transition-colors motion-reduce:transition-none"
                :disabled="busy"
                @click="changeAccount"
            >
                Ganti akun Telegram
            </Button>
        </div>

        <p class="text-center text-sm text-muted-foreground">
            <Link
                :href="home()"
                class="inline-flex min-h-11 items-center rounded-sm px-2 underline underline-offset-4 transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:transition-none"
            >
                Kembali ke beranda
            </Link>
        </p>
    </form>
</template>
