<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    Bot,
    Check,
    MessageCircle,
    ShieldCheck,
} from '@lucide/vue';

const page = usePage();
const destination = () =>
    page.props.auth.user ? '/dashboard' : '/telegram-login';
const primaryLabel = () =>
    page.props.auth.user ? 'Buka dashboard' : 'Hubungkan Telegram';

const steps = [
    {
        number: '01',
        title: 'Mulai bot Telegram',
        text: 'Buka bot DuitBot, kirim /start, lalu hubungkan akunmu dengan kode masuk sekali pakai.',
    },
    {
        number: '02',
        title: 'Kirim seperti chat biasa',
        text: 'Tulis pengeluaran dengan bahasamu sendiri. DuitBot akan mengirim hasilnya untuk kamu periksa.',
    },
    {
        number: '03',
        title: 'Periksa dan lihat polanya',
        text: 'Pilih kategori jika DuitBot ragu, lalu buka dashboard untuk melihat riwayat dan kondisi budget.',
    },
];

const benefits = [
    {
        icon: MessageCircle,
        title: 'Mudah dibiasakan',
        text: 'Pencatatan terjadi di Telegram, tempat percakapan harianmu sudah berlangsung.',
    },
    {
        icon: BarChart3,
        title: 'Ringkas saat dibutuhkan',
        text: 'Grafik dan kategori membantu menjawab ke mana uangmu pergi tanpa rekap manual.',
    },
    {
        icon: ShieldCheck,
        title: 'Budget lebih terjaga',
        text: 'Peringatan pada 80% dan 100% membantu kamu bertindak sebelum pengeluaran kebablasan.',
    },
];

const recoveryGuidance = [
    {
        title: 'Nominal tidak terbaca',
        text: 'DuitBot memberi contoh format yang dapat dicoba lagi, seperti “Makan siang 25rb”.',
    },
    {
        title: 'Kategori kurang tepat',
        text: 'Pilih kategori yang benar langsung dari balasan bot. Perubahannya akan dikonfirmasi.',
    },
    {
        title: 'Balasan belum muncul',
        text: 'Anggap transaksi tersimpan hanya setelah balasan “Tercatat” muncul. Jika belum ada, periksa koneksi lalu kirim ulang.',
    },
    {
        title: 'Transaksi perlu diperbaiki',
        text: 'Ubah deskripsi, nominal, atau kategori—atau hapus transaksi—melalui dashboard.',
    },
];
</script>

<template>
    <Head title="Catat pengeluaran lewat Telegram" />
    <main
        id="konten-utama"
        class="min-h-screen overflow-x-hidden bg-background text-foreground"
    >
        <a
            href="#hero"
            class="sr-only z-50 rounded-md bg-background px-4 py-3 font-semibold focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:ring-2 focus:ring-ring focus:outline-none"
        >
            Lewati ke konten utama
        </a>
        <nav
            aria-label="Navigasi utama"
            class="mx-auto flex max-w-6xl items-center justify-between px-5 py-5 sm:px-6 sm:py-6"
        >
            <Link
                href="/"
                aria-label="DuitBot, kembali ke beranda"
                class="inline-flex min-h-11 items-center gap-3 rounded-lg font-bold focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-offset-slate-950"
            >
                <img
                    src="/brand/duitbot-logo.png"
                    alt=""
                    width="44"
                    height="44"
                    class="size-11 rounded-xl object-cover"
                />
                <span class="text-lg">DuitBot</span>
            </Link>
            <Link
                :href="destination()"
                class="inline-flex min-h-11 items-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white transition-colors hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 focus-visible:outline-none motion-reduce:transition-none sm:px-5 dark:bg-teal-400 dark:text-teal-950 dark:hover:bg-teal-300 dark:focus-visible:ring-offset-slate-950"
            >
                {{ primaryLabel() }}
            </Link>
        </nav>

        <section
            id="hero"
            class="mx-auto grid max-w-6xl items-center gap-14 px-5 pt-16 pb-20 sm:px-6 sm:pt-20 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16 lg:pt-24 lg:pb-28"
        >
            <div>
                <p
                    class="text-base font-semibold text-teal-700 dark:text-teal-300"
                >
                    Keuangan pribadi, lewat percakapan.
                </p>
                <h1
                    class="mt-5 max-w-3xl text-5xl leading-[1.05] font-black tracking-[-0.035em] text-balance sm:text-6xl lg:text-[4rem]"
                >
                    Catat pengeluaran secepat kirim chat.
                </h1>
                <p
                    class="mt-6 max-w-2xl text-lg leading-8 text-pretty text-slate-600 dark:text-slate-300"
                >
                    Kirim “Makan siang 25rb” ke Telegram. DuitBot mencatat,
                    mengelompokkan, lalu menyajikan pola pengeluaranmu ketika
                    kamu membutuhkannya.
                </p>
                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <Link
                        :href="destination()"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-lg bg-teal-600 px-6 font-semibold text-white transition-colors hover:bg-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 focus-visible:outline-none motion-reduce:transition-none dark:bg-teal-400 dark:text-teal-950 dark:hover:bg-teal-300 dark:focus-visible:ring-offset-slate-950"
                    >
                        {{ primaryLabel() }}
                        <ArrowRight aria-hidden="true" class="size-4" />
                    </Link>
                    <a
                        href="#cara-kerja"
                        class="inline-flex h-12 items-center justify-center rounded-lg border border-slate-300 px-6 font-semibold transition-colors hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 focus-visible:outline-none motion-reduce:transition-none dark:border-slate-700 dark:hover:bg-slate-900 dark:focus-visible:ring-offset-slate-950"
                    >
                        Lihat cara kerja
                    </a>
                </div>
                <p
                    class="mt-5 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400"
                >
                    <Check aria-hidden="true" class="size-4 text-teal-600" />
                    Berikutnya: mulai bot dan verifikasi dengan kode 6 digit.
                </p>
            </div>

            <div class="relative mx-auto w-full max-w-xl">
                <div
                    aria-hidden="true"
                    class="absolute -inset-12 -z-10 bg-teal-400/20 blur-3xl dark:bg-teal-400/10"
                />
                <div
                    class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-[0_8px_8px_rgb(15_23_42/0.14)] dark:bg-slate-900"
                >
                    <div
                        class="flex items-center gap-3 border-b border-white/10 px-5 py-4"
                    >
                        <span
                            class="grid size-11 place-items-center rounded-xl bg-teal-400 text-teal-950"
                        >
                            <Bot aria-hidden="true" class="size-6" />
                        </span>
                        <div>
                            <strong class="block leading-tight"
                                >DuitBot Tracker</strong
                            >
                            <p
                                class="mt-1 flex items-center gap-1.5 text-xs text-teal-300"
                            >
                                <span
                                    aria-hidden="true"
                                    class="size-1.5 rounded-full bg-teal-300"
                                />
                                siap mencatat
                            </p>
                        </div>
                    </div>
                    <div class="space-y-4 p-5 sm:p-6">
                        <div
                            class="ml-auto max-w-[82%] rounded-xl rounded-br-sm bg-sky-500 px-4 py-3 text-sm"
                        >
                            Makan siang 25rb
                        </div>
                        <div
                            class="max-w-[90%] rounded-xl rounded-bl-sm bg-slate-800 px-4 py-3 text-sm leading-6"
                        >
                            <p class="flex items-center gap-2 text-teal-300">
                                <Check aria-hidden="true" class="size-4" />
                                Tercatat
                            </p>
                            <strong class="mt-1 block">Makan siang</strong>
                            <span class="text-slate-300"
                                >Rp25.000 · Makanan</span
                            >
                        </div>
                    </div>
                </div>
                <p
                    class="mt-4 text-center text-sm text-slate-500 dark:text-slate-400"
                >
                    Jika kategorinya kurang pasti, DuitBot akan meminta
                    pilihanmu.
                </p>
            </div>
        </section>

        <section
            id="cara-kerja"
            class="scroll-mt-8 bg-slate-950 py-20 text-white sm:py-24"
        >
            <div class="mx-auto max-w-6xl px-5 sm:px-6">
                <div class="max-w-2xl">
                    <p class="font-semibold text-teal-300">Cara kerja</p>
                    <h2
                        class="mt-4 text-3xl font-bold tracking-tight text-balance sm:text-5xl"
                    >
                        Dari percakapan menjadi keputusan yang lebih baik.
                    </h2>
                </div>
                <ol class="mt-14 border-t border-white/15">
                    <li
                        v-for="step in steps"
                        :key="step.number"
                        class="grid gap-3 border-b border-white/15 py-7 sm:grid-cols-[5rem_0.8fr_1.2fr] sm:items-start sm:gap-8 sm:py-8"
                    >
                        <span class="text-sm font-semibold text-teal-300">{{
                            step.number
                        }}</span>
                        <h3 class="text-xl font-semibold">{{ step.title }}</h3>
                        <p class="max-w-xl leading-7 text-slate-300">
                            {{ step.text }}
                        </p>
                    </li>
                </ol>

                <div
                    class="mt-16 grid border-t border-white/15 pt-12 lg:grid-cols-[1.05fr_0.95fr]"
                >
                    <div
                        class="border-b border-white/15 pb-10 lg:border-r lg:border-b-0 lg:pr-10 lg:pb-0"
                    >
                        <p class="font-semibold text-teal-300">
                            Saat kategori belum jelas
                        </p>
                        <h3
                            class="mt-3 text-2xl font-bold tracking-tight text-white"
                        >
                            DuitBot bertanya, bukan menebak diam-diam.
                        </h3>
                        <div
                            class="mt-7 space-y-4 rounded-xl bg-slate-900 p-4 text-sm text-white"
                            aria-label="Contoh percakapan koreksi kategori"
                        >
                            <p
                                class="ml-auto max-w-[82%] rounded-xl rounded-br-sm bg-sky-500 px-4 py-3"
                            >
                                Servis motor 350rb
                            </p>
                            <div
                                class="max-w-[92%] rounded-xl rounded-bl-sm bg-slate-800 px-4 py-3 leading-6"
                            >
                                <p>
                                    ✅ Tercatat: <strong>Servis motor</strong>
                                </p>
                                <p class="text-slate-300">
                                    Rp350.000 · Lainnya
                                </p>
                                <p class="mt-2 text-amber-200">
                                    Pilih kategori yang lebih tepat:
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        class="rounded-md bg-teal-400 px-3 py-2 text-xs font-semibold text-teal-950"
                                        >Transport</span
                                    >
                                    <span
                                        class="rounded-md bg-slate-700 px-3 py-2 text-xs"
                                        >Belanja</span
                                    >
                                    <span
                                        class="rounded-md bg-slate-700 px-3 py-2 text-xs"
                                        >Lainnya</span
                                    >
                                </div>
                            </div>
                            <p class="text-xs text-teal-300">
                                ✅ Kategori diubah menjadi Transport.
                            </p>
                        </div>
                    </div>

                    <div class="pt-10 lg:pt-0 lg:pl-10">
                        <h3 class="text-xl font-bold text-white">
                            Jika sesuatu tidak beres
                        </h3>
                        <dl
                            class="mt-5 divide-y divide-white/15 border-y border-white/15"
                        >
                            <div
                                v-for="item in recoveryGuidance"
                                :key="item.title"
                                class="py-4"
                            >
                                <dt class="font-semibold text-white">
                                    {{ item.title }}
                                </dt>
                                <dd class="mt-1 leading-6 text-slate-300">
                                    {{ item.text }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </section>

        <section
            id="privasi"
            class="scroll-mt-8 border-y border-slate-200 bg-slate-50 py-20 sm:py-24 dark:border-slate-800 dark:bg-slate-900/50"
        >
            <div
                class="mx-auto grid max-w-6xl gap-12 px-5 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:gap-20"
            >
                <div>
                    <p class="font-semibold text-teal-700 dark:text-teal-300">
                        Kendali tetap di tanganmu
                    </p>
                    <h2
                        class="mt-4 text-3xl font-bold tracking-tight text-balance sm:text-5xl"
                    >
                        Hubungkan akun tanpa menyerahkan password Telegram.
                    </h2>
                    <p
                        class="mt-5 max-w-xl leading-7 text-slate-600 dark:text-slate-300"
                    >
                        DuitBot memakai identitas Telegram untuk mengenali akun
                        dan mengirim kode masuk—bukan untuk membaca percakapan
                        pribadimu.
                    </p>
                </div>
                <div class="border-t border-slate-300 dark:border-slate-700">
                    <article
                        class="grid gap-3 border-b border-slate-300 py-6 sm:grid-cols-[10rem_1fr] sm:gap-8 dark:border-slate-700"
                    >
                        <h3 class="font-semibold">Saat terhubung</h3>
                        <p class="leading-7 text-slate-600 dark:text-slate-300">
                            DuitBot menyimpan Telegram ID atau username untuk
                            mengenali akunmu. Password Telegram tidak pernah
                            diminta.
                        </p>
                    </article>
                    <article
                        class="grid gap-3 border-b border-slate-300 py-6 sm:grid-cols-[10rem_1fr] sm:gap-8 dark:border-slate-700"
                    >
                        <h3 class="font-semibold">Saat masuk</h3>
                        <p class="leading-7 text-slate-600 dark:text-slate-300">
                            Kode masuk dikirim melalui bot, berlaku selama lima
                            menit, dan hanya dapat digunakan satu kali.
                        </p>
                    </article>
                    <article
                        class="grid gap-3 border-b border-slate-300 py-6 sm:grid-cols-[10rem_1fr] sm:gap-8 dark:border-slate-700"
                    >
                        <h3 class="font-semibold">Setelah mencatat</h3>
                        <p class="leading-7 text-slate-600 dark:text-slate-300">
                            Transaksi hanya tersedia untuk akunmu. Kamu dapat
                            mengubah atau menghapus transaksi di dashboard dan
                            menghapus akun melalui Pengaturan.
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="py-20 sm:py-24">
            <div
                class="mx-auto grid max-w-6xl gap-12 px-5 sm:px-6 lg:grid-cols-[0.8fr_1.2fr] lg:gap-20"
            >
                <div>
                    <p class="font-semibold text-teal-700 dark:text-teal-300">
                        Lebih sedikit repot
                    </p>
                    <h2
                        class="mt-4 text-3xl font-bold tracking-tight text-balance sm:text-5xl"
                    >
                        Fokus memahami uangmu, bukan mengurus catatannya.
                    </h2>
                </div>
                <div class="border-t border-slate-200 dark:border-slate-800">
                    <article
                        v-for="benefit in benefits"
                        :key="benefit.title"
                        class="grid gap-4 border-b border-slate-200 py-7 sm:grid-cols-[3rem_1fr] dark:border-slate-800"
                    >
                        <component
                            :is="benefit.icon"
                            aria-hidden="true"
                            class="size-6 text-teal-600 dark:text-teal-300"
                        />
                        <div>
                            <h3 class="text-lg font-semibold">
                                {{ benefit.title }}
                            </h3>
                            <p
                                class="mt-2 max-w-xl leading-7 text-slate-600 dark:text-slate-300"
                            >
                                {{ benefit.text }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="px-5 pb-20 sm:px-6 sm:pb-24">
            <div
                class="mx-auto flex max-w-6xl flex-col items-start justify-between gap-8 rounded-2xl bg-teal-500 p-7 text-teal-950 sm:p-10 lg:flex-row lg:items-center"
            >
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-bold tracking-tight text-balance">
                        Siap menghubungkan Telegram?
                    </h2>
                    <p class="mt-3 text-lg leading-8 text-teal-950/80">
                        Mulai bot, masukkan akun Telegram, lalu gunakan kode
                        masuk yang dikirim DuitBot.
                    </p>
                </div>
                <Link
                    :href="destination()"
                    class="inline-flex h-12 shrink-0 items-center gap-2 rounded-lg bg-slate-950 px-6 font-semibold text-white transition-colors hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2 focus-visible:ring-offset-teal-500 focus-visible:outline-none motion-reduce:transition-none"
                >
                    {{ primaryLabel() }}
                    <ArrowRight aria-hidden="true" class="size-4" />
                </Link>
            </div>
        </section>

        <footer
            class="border-t border-slate-200 px-5 py-7 text-sm text-slate-500 sm:px-6 dark:border-slate-800 dark:text-slate-400"
        >
            <div
                class="mx-auto flex max-w-6xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <p>DuitBot Tracker</p>
                <nav aria-label="Tautan footer" class="flex flex-wrap gap-5">
                    <a
                        href="#cara-kerja"
                        class="inline-flex min-h-11 items-center rounded-sm underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >Cara kerja</a
                    >
                    <a
                        href="#privasi"
                        class="inline-flex min-h-11 items-center rounded-sm underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >Privasi dan kendali data</a
                    >
                </nav>
            </div>
        </footer>
    </main>
</template>
