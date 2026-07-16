<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertCircle,
    CalendarDays,
    ChartNoAxesCombined,
    ChevronLeft,
    ChevronRight,
    Inbox,
    Lightbulb,
    Pencil,
    RefreshCw,
    Search,
    Target,
    Trash2,
    TrendingDown,
    TrendingUp,
    WalletCards,
} from '@lucide/vue';
import { useMediaQuery, useMounted } from '@vueuse/core';
import Chart from 'chart.js/auto';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
} from 'vue';
import { toast } from 'vue-sonner';
import CurrencyInput from '@/components/CurrencyInput.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

defineOptions({
    inheritAttrs: false,
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

const props = defineProps<{ categories: string[] }>();

type Tx = {
    id: number;
    amount: number;
    category: string;
    description: string;
    source: string;
    occurred_at: string;
};

type Budget = {
    id: number;
    category: string;
    monthly_limit: number;
    spent: number;
    percentage: number;
};

type Summary = {
    month: string;
    first_transaction_month: string | null;
    total: number;
    transaction_count: number;
    previous_total: number;
    change_percent: number | null;
    daily: Record<string, number>;
    categories: Record<string, number>;
    previous_categories: Record<string, number>;
};

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

const mobileTabs = [
    { id: 'summary', label: 'Ringkasan' },
    { id: 'transactions', label: 'Transaksi' },
    { id: 'budget', label: 'Budget' },
] as const;
type MobileTabId = (typeof mobileTabs)[number]['id'];

const summary = ref<Summary | null>(null);
const transactions = ref<Tx[]>([]);
const budgets = ref<Budget[]>([]);
const loading = ref(true);
const periodLoading = ref(false);
const pendingMonth = ref<string | null>(null);
const transactionLoading = ref(false);
const refreshing = ref(false);
const savingTransaction = ref(false);
const savingBudget = ref(false);
const error = ref('');
const activeMobileTab = ref<MobileTabId>('summary');
const mounted = useMounted();
const mobileMediaQuery = useMediaQuery('(max-width: 767px)');
const isMobileDashboard = computed(
    () => mounted.value && mobileMediaQuery.value,
);
const mobileTabButtons =
    useTemplateRef<HTMLButtonElement[]>('mobileTabButtons');
const pagination = ref<Pagination>({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: null,
    to: null,
});
const search = ref('');
const category = ref('');
const from = ref('');
const to = ref('');
const editing = ref<Tx | null>(null);
const editAmount = ref('');
const editDescription = ref('');
const editCategory = ref('');
const editAmountError = ref('');
const editDescriptionError = ref('');
const budgetCategory = ref(props.categories[0] ?? '');
const categorySelectValue = computed<string>({
    get: () => category.value || 'all',
    set: (value) => {
        category.value = value === 'all' ? '' : value;
    },
});
const budgetLimit = ref('500000');
const budgetLimitError = ref('');
const currentMonth = (() => {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
    }).formatToParts(new Date());
    const year = parts.find((part) => part.type === 'year')?.value;
    const month = parts.find((part) => part.type === 'month')?.value;

    return `${year}-${month}`;
})();
const selectedMonth = ref(currentMonth);
const dailyChartContainer = ref<HTMLDivElement>();
const categoryChartContainer = ref<HTMLDivElement>();
const dailyCanvas = ref<HTMLCanvasElement>();
const categoryCanvas = ref<HTMLCanvasElement>();
let dailyChart: Chart | null = null;
let categoryChart: Chart | null = null;
let chartResizeObserver: ResizeObserver | null = null;
let chartResizeFrame: number | null = null;

const rupiah = (value: number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);

const monthDate = (value: string) => {
    const [year, month] = value.split('-').map(Number);

    return new Date(year, month - 1, 1);
};

const monthKey = (date: Date) =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;

const formatMonth = (value: string, includeYear = true) =>
    new Intl.DateTimeFormat('id-ID', {
        month: 'long',
        ...(includeYear ? { year: 'numeric' } : {}),
    }).format(monthDate(value));

const shiftMonth = (value: string, offset: number) => {
    const date = monthDate(value);
    date.setMonth(date.getMonth() + offset);

    return monthKey(date);
};

const categoryEmoji = (value: string) =>
    (
        ({
            Makanan: '🍜',
            Transport: '🚕',
            Belanja: '🛍️',
            Tagihan: '🧾',
            Hiburan: '🎮',
            Kesehatan: '💊',
            Pendidikan: '🎓',
            'Tempat Tinggal': '🏠',
            'Rumah Tangga': '🧹',
            'Perawatan Pribadi': '✂️',
            'Keluarga & Anak': '👨‍👩‍👧',
            'Hewan Peliharaan': '🐾',
            Asuransi: '🛡️',
            'Cicilan & Utang': '💳',
            'Pajak & Administrasi': '📑',
            Tabungan: '🏦',
            Investasi: '📈',
            'Donasi & Sosial': '🤝',
            Hadiah: '🎁',
            Perjalanan: '✈️',
            'Pekerjaan & Bisnis': '💼',
            Olahraga: '🏃',
            Teknologi: '💻',
            Lainnya: '📦',
        }) as Record<string, string>
    )[value] ?? '📦';

const csrf = () => {
    const hit = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return hit ? decodeURIComponent(hit[1]) : '';
};

async function api(url: string, options: RequestInit = {}) {
    if (options.method && options.method !== 'GET') {
        await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    }

    const response = await fetch(url, {
        credentials: 'include',
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': csrf(),
            ...(options.headers || {}),
        },
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));

        throw new Error(body.message || 'Permintaan gagal. Coba lagi.');
    }

    return response.status === 204 ? null : response.json();
}

async function fetchSummary(month = selectedMonth.value): Promise<Summary> {
    const body = await api(`/api/summary/monthly?month=${month}`);

    return body.data;
}

async function loadSummary() {
    summary.value = await fetchSummary();
}

async function loadTransactions(page = 1) {
    transactionLoading.value = true;
    const params = new URLSearchParams({
        per_page: String(pagination.value.per_page),
        page: String(page),
    });

    if (search.value.trim()) {
        params.set('search', search.value.trim());
    }

    if (category.value) {
        params.set('category', category.value);
    }

    if (from.value) {
        params.set('from', from.value);
    }

    if (to.value) {
        params.set('to', to.value);
    }

    try {
        const body = await api('/api/transactions?' + params);
        transactions.value = body.data;
        pagination.value = body.meta;
    } finally {
        transactionLoading.value = false;
    }
}

async function fetchBudgets(month = selectedMonth.value): Promise<Budget[]> {
    const body = await api(`/api/budgets/status?month=${month}`);

    return body.data;
}

async function loadBudgets() {
    budgets.value = await fetchBudgets();
}

async function changePeriod(month: string) {
    const firstMonth = summary.value?.first_transaction_month ?? currentMonth;

    if (
        periodLoading.value ||
        month === selectedMonth.value ||
        month < firstMonth ||
        month > currentMonth
    ) {
        return;
    }

    periodLoading.value = true;
    pendingMonth.value = month;

    try {
        const [nextSummary, nextBudgets] = await Promise.all([
            fetchSummary(month),
            fetchBudgets(month),
        ]);
        selectedMonth.value = month;
        summary.value = nextSummary;
        budgets.value = nextBudgets;
        await nextTick();
        renderCharts();
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        periodLoading.value = false;
        pendingMonth.value = null;
    }
}

function selectPeriod(value: unknown) {
    if (typeof value === 'string') {
        void changePeriod(value);
    }
}

function showPreviousMonth() {
    void changePeriod(shiftMonth(selectedMonth.value, -1));
}

function showNextMonth() {
    void changePeriod(shiftMonth(selectedMonth.value, 1));
}

async function loadAll() {
    loading.value = true;
    refreshing.value = true;
    error.value = '';

    try {
        await Promise.all([loadSummary(), loadTransactions(), loadBudgets()]);
    } catch (e) {
        error.value = (e as Error).message;
    } finally {
        loading.value = false;
        refreshing.value = false;
        await nextTick();
        renderCharts();
    }
}

function renderCharts() {
    if (!summary.value || !dailyCanvas.value || !categoryCanvas.value) {
        return;
    }

    dailyChart?.destroy();
    categoryChart?.destroy();

    const isDark = document.documentElement.classList.contains('dark');
    const labelColor = isDark ? '#d4d4d8' : '#52525b';
    const gridColor = isDark ? '#27272a' : '#e4e4e7';
    const teal = '#0d9488';
    const dailyValues = Object.values(summary.value.daily);

    dailyChart = new Chart(dailyCanvas.value, {
        type: 'bar',
        data: {
            labels: Object.keys(summary.value.daily),
            datasets: [
                {
                    label: 'Pengeluaran',
                    data: dailyValues,
                    backgroundColor: teal,
                    borderRadius: 4,
                    borderSkipped: false,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 100,
            animation: { duration: 180 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => rupiah(Number(context.raw)),
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: labelColor, maxRotation: 0 },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: {
                        color: labelColor,
                        callback: (value) =>
                            new Intl.NumberFormat('id-ID', {
                                notation: 'compact',
                                maximumFractionDigits: 1,
                            }).format(Number(value)),
                    },
                },
            },
        },
    });

    const entries = Object.entries(summary.value.categories).filter(
        ([, value]) => value > 0,
    );

    categoryChart = new Chart(categoryCanvas.value, {
        type: 'doughnut',
        data: {
            labels: entries.map(([key]) => key),
            datasets: [
                {
                    data: entries.map(([, value]) => value),
                    backgroundColor: [
                        '#0d9488',
                        '#0369a1',
                        '#b45309',
                        '#6d28d9',
                        '#be185d',
                        '#b91c1c',
                        '#475569',
                    ],
                    borderWidth: 0,
                    hoverOffset: 3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 100,
            animation: { duration: 180 },
            cutout: '66%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: labelColor,
                        boxWidth: 10,
                        boxHeight: 10,
                        padding: 14,
                        usePointStyle: true,
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (context) =>
                            `${context.label}: ${rupiah(Number(context.raw))}`,
                    },
                },
            },
        },
    });

    observeChartContainers();
}

function resizeCharts() {
    if (chartResizeFrame !== null) {
        cancelAnimationFrame(chartResizeFrame);
    }

    chartResizeFrame = requestAnimationFrame(() => {
        dailyChart?.resize();
        categoryChart?.resize();
        chartResizeFrame = null;
    });
}

function observeChartContainers() {
    chartResizeObserver?.disconnect();

    if (!dailyChartContainer.value || !categoryChartContainer.value) {
        return;
    }

    chartResizeObserver = new ResizeObserver(resizeCharts);
    chartResizeObserver.observe(dailyChartContainer.value);
    chartResizeObserver.observe(categoryChartContainer.value);
    resizeCharts();
}

function beginEdit(tx: Tx) {
    editing.value = tx;
    editAmount.value = String(tx.amount);
    editDescription.value = tx.description;
    editCategory.value = tx.category;
    editAmountError.value = '';
    editDescriptionError.value = '';
}

function setEditDialog(open: boolean) {
    if (!open && !savingTransaction.value) {
        editing.value = null;
        editAmountError.value = '';
        editDescriptionError.value = '';
    }
}

async function saveEdit() {
    if (!editing.value) {
        return;
    }

    editDescriptionError.value = editDescription.value.trim()
        ? ''
        : 'Masukkan deskripsi transaksi.';
    editAmountError.value = /^[1-9]\d*$/.test(editAmount.value)
        ? ''
        : 'Masukkan nominal minimal Rp1.';

    if (editDescriptionError.value || editAmountError.value) {
        return;
    }

    savingTransaction.value = true;

    try {
        await api(`/api/transactions/${editing.value.id}`, {
            method: 'PUT',
            body: JSON.stringify({
                amount: Number(editAmount.value),
                description: editDescription.value.trim(),
                category: editCategory.value,
            }),
        });
        editing.value = null;
        await Promise.all([
            loadTransactions(pagination.value.current_page),
            loadSummary(),
            loadBudgets(),
        ]);
        await nextTick();
        renderCharts();
        toast.success('Transaksi berhasil diperbarui.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        savingTransaction.value = false;
    }
}

async function removeTx(tx: Tx) {
    if (!confirm(`Hapus transaksi “${tx.description}”?`)) {
        return;
    }

    try {
        await api(`/api/transactions/${tx.id}`, { method: 'DELETE' });
        const targetPage =
            transactions.value.length === 1 && pagination.value.current_page > 1
                ? pagination.value.current_page - 1
                : pagination.value.current_page;
        await Promise.all([
            loadTransactions(targetPage),
            loadSummary(),
            loadBudgets(),
        ]);
        await nextTick();
        renderCharts();
        toast.success('Transaksi berhasil dihapus.');
    } catch (e) {
        toast.error((e as Error).message);
    }
}

async function saveBudget() {
    budgetLimitError.value = /^[1-9]\d*$/.test(budgetLimit.value)
        ? ''
        : 'Masukkan batas bulanan minimal Rp1.';

    if (budgetLimitError.value || !budgetCategory.value) {
        return;
    }

    savingBudget.value = true;

    try {
        await api('/api/budgets', {
            method: 'POST',
            body: JSON.stringify({
                category: budgetCategory.value,
                monthly_limit: Number(budgetLimit.value),
            }),
        });
        await loadBudgets();
        toast.success('Budget bulanan berhasil disimpan.');
    } catch (e) {
        toast.error((e as Error).message);
    } finally {
        savingBudget.value = false;
    }
}

async function removeBudget(item: Budget) {
    if (!confirm(`Hapus budget ${item.category}?`)) {
        return;
    }

    try {
        await api(`/api/budgets/${item.id}`, { method: 'DELETE' });
        await loadBudgets();
        toast.success(`Budget ${item.category} berhasil dihapus.`);
    } catch (e) {
        toast.error((e as Error).message);
    }
}

function applyFilters() {
    error.value = '';
    loadTransactions(1).catch((e) => {
        error.value = (e as Error).message;
    });
}

function resetFilters() {
    search.value = '';
    category.value = '';
    from.value = '';
    to.value = '';
    applyFilters();
}

function goToPage(page: number) {
    if (
        page < 1 ||
        page > pagination.value.last_page ||
        page === pagination.value.current_page
    ) {
        return;
    }

    loadTransactions(page).catch((e) => {
        error.value = (e as Error).message;
    });
}

function activateMobileTab(index: number, moveFocus = false) {
    const tab = mobileTabs[index];

    if (!tab) {
        return;
    }

    activeMobileTab.value = tab.id;

    nextTick(() => {
        if (tab.id === 'summary') {
            resizeCharts();
        }

        if (moveFocus) {
            mobileTabButtons.value?.[index]?.focus();
        }

        // Naik ke paling atas halaman saat ganti tab, supaya tab + header terlihat
        // penuh (bukan konten yang mepet tepat di bawah tab).
        window.scrollTo({ top: 0 });
    });
}

function handleMobileTabKeydown(event: KeyboardEvent, index: number) {
    let targetIndex: number | null = null;

    if (event.key === 'ArrowRight') {
        targetIndex = (index + 1) % mobileTabs.length;
    } else if (event.key === 'ArrowLeft') {
        targetIndex = (index - 1 + mobileTabs.length) % mobileTabs.length;
    } else if (event.key === 'Home') {
        targetIndex = 0;
    } else if (event.key === 'End') {
        targetIndex = mobileTabs.length - 1;
    }

    if (targetIndex === null) {
        return;
    }

    event.preventDefault();
    activateMobileTab(targetIndex, true);
}

const budgetTotal = computed(() =>
    budgets.value.reduce((sum, budget) => sum + budget.monthly_limit, 0),
);
const budgetSpent = computed(() =>
    budgets.value.reduce((sum, budget) => sum + budget.spent, 0),
);
const budgetRemaining = computed(() =>
    Math.max(budgetTotal.value - budgetSpent.value, 0),
);
const hasActiveFilters = computed(() =>
    Boolean(search.value || category.value || from.value || to.value),
);
const hasDailyData = computed(() =>
    Object.values(summary.value?.daily ?? {}).some((value) => value > 0),
);
const hasCategoryData = computed(() =>
    Object.values(summary.value?.categories ?? {}).some((value) => value > 0),
);
const firstAvailableMonth = computed(
    () => summary.value?.first_transaction_month ?? currentMonth,
);
const isCurrentMonth = computed(() => selectedMonth.value === currentMonth);
const canShowPreviousMonth = computed(
    () =>
        !periodLoading.value && selectedMonth.value > firstAvailableMonth.value,
);
const canShowNextMonth = computed(
    () => !periodLoading.value && selectedMonth.value < currentMonth,
);
const monthOptions = computed(() => {
    const options: Array<{ value: string; label: string }> = [];
    const earliest = monthDate(firstAvailableMonth.value);
    const cursor = monthDate(currentMonth);

    while (cursor >= earliest) {
        const value = monthKey(cursor);
        options.push({ value, label: formatMonth(value) });
        cursor.setMonth(cursor.getMonth() - 1);
    }

    return options;
});
const monthLabel = computed(() => formatMonth(selectedMonth.value));
const compactMonthLabel = computed(() =>
    new Intl.DateTimeFormat('id-ID', {
        month: 'short',
        year: 'numeric',
    }).format(monthDate(selectedMonth.value)),
);
const selectedMonthName = computed(() =>
    formatMonth(selectedMonth.value, false),
);
const summaryTitle = computed(() => `Ringkasan ${selectedMonthName.value}`);
const transactionPeriodText = computed(
    () => `Semua transaksi bulan ${selectedMonthName.value}`,
);
const budgetPeriodText = computed(() => {
    if (!budgets.value.length) {
        return 'Belum ada budget yang diatur';
    }

    return isCurrentMonth.value
        ? `${budgets.value.length} kategori diatur`
        : `Menggunakan ${budgets.value.length} budget aktif saat ini`;
});
const emptyPeriodHelp = computed(() =>
    isCurrentMonth.value
        ? 'Catat pengeluaran melalui Telegram agar ringkasanmu mulai terbentuk.'
        : 'Pilih periode lain untuk melihat pola pengeluaranmu.',
);
const previousMonthLabel = computed(() =>
    formatMonth(shiftMonth(selectedMonth.value, -1)),
);
const nextMonthLabel = computed(() =>
    formatMonth(shiftMonth(selectedMonth.value, 1)),
);
const loadingMonthLabel = computed(() =>
    formatMonth(pendingMonth.value ?? selectedMonth.value),
);
const comparisonText = computed(() => {
    if ((summary.value?.transaction_count ?? 0) === 0) {
        return `Belum ada transaksi pada ${selectedMonthName.value}`;
    }

    const change = summary.value?.change_percent;

    if (change === null || change === undefined) {
        return 'Belum ada data pembanding bulan lalu';
    }

    return `${change >= 0 ? 'Naik' : 'Turun'} ${Math.abs(change)}% dari bulan lalu`;
});

const spendingNarrative = computed<string | null>(() => {
    if (!summary.value || summary.value.transaction_count === 0) {
        return null;
    }

    const total = summary.value.total;
    const entries = Object.entries(summary.value.categories).filter(
        ([, value]) => value > 0,
    );

    if (!entries.length || total <= 0) {
        return null;
    }

    const [topCategory, topAmount] = entries.reduce((max, entry) =>
        entry[1] > max[1] ? entry : max,
    );
    const share = Math.round((topAmount / total) * 100);
    let sentence = `Pengeluaran terbesarmu ada di ${topCategory} — ${rupiah(topAmount)}, sekitar ${share}% dari total.`;

    const change = summary.value.change_percent;

    if (change !== null && change !== undefined) {
        sentence +=
            change >= 0
                ? ` Total bulan ini naik ${Math.abs(change)}% dibanding bulan lalu.`
                : ` Total bulan ini turun ${Math.abs(change)}% dibanding bulan lalu.`;
    }

    return sentence;
});

const projection = computed<{
    amount: number;
    overBudget: boolean;
    remainingDays: number;
} | null>(() => {
    if (!summary.value || !isCurrentMonth.value || summary.value.total <= 0) {
        return null;
    }

    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'Asia/Jakarta',
        day: '2-digit',
    }).formatToParts(new Date());
    const today = Number(parts.find((part) => part.type === 'day')?.value);
    const daysInMonth = monthDate(shiftMonth(selectedMonth.value, 1));
    daysInMonth.setDate(0);
    const totalDays = daysInMonth.getDate();

    if (!today || today < 1) {
        return null;
    }

    const amount = Math.round((summary.value.total / today) * totalDays);

    return {
        amount,
        overBudget: budgetTotal.value > 0 && amount > budgetTotal.value,
        remainingDays: Math.max(totalDays - today, 0),
    };
});

const categoryTrends = computed<
    Array<{
        category: string;
        deltaAmount: number;
        deltaPercent: number | null;
        direction: 'up' | 'down';
    }>
>(() => {
    if (!summary.value) {
        return [];
    }

    const current = summary.value.categories;
    const previous = summary.value.previous_categories ?? {};

    return Object.keys(current)
        .map((category) => {
            const now = current[category] ?? 0;
            const before = previous[category] ?? 0;
            const deltaAmount = now - before;

            return {
                category,
                deltaAmount,
                deltaPercent:
                    before > 0
                        ? Math.round((deltaAmount / before) * 100)
                        : null,
                direction: (deltaAmount >= 0 ? 'up' : 'down') as 'up' | 'down',
            };
        })
        .filter((trend) => trend.deltaAmount !== 0)
        .sort((a, b) => Math.abs(b.deltaAmount) - Math.abs(a.deltaAmount))
        .slice(0, 2);
});

const categoryTrendText = (trend: {
    category: string;
    deltaAmount: number;
    deltaPercent: number | null;
    direction: 'up' | 'down';
}) => {
    const magnitude = rupiah(Math.abs(trend.deltaAmount));

    if (trend.direction === 'down') {
        const percent =
            trend.deltaPercent !== null
                ? ` ${Math.abs(trend.deltaPercent)}%`
                : '';

        return `${trend.category} turun${percent}, hemat ${magnitude}`;
    }

    if (trend.deltaPercent === null) {
        return `${trend.category} mulai muncul, ${magnitude}`;
    }

    return `${trend.category} naik ${Math.abs(trend.deltaPercent)}%, ${magnitude} lebih banyak`;
};

onMounted(loadAll);
onBeforeUnmount(() => {
    chartResizeObserver?.disconnect();

    if (chartResizeFrame !== null) {
        cancelAnimationFrame(chartResizeFrame);
    }

    dailyChart?.destroy();
    categoryChart?.destroy();
});
</script>

<template>
    <Head title="Dashboard Keuangan" />

    <main
        id="main-content"
        class="mx-auto flex w-full max-w-[1480px] flex-1 flex-col gap-6 overflow-x-hidden p-4 sm:p-6 lg:p-8"
    >
        <header
            class="hidden flex-col justify-between gap-4 lg:flex lg:flex-row lg:items-end"
        >
            <div class="min-w-0">
                <div
                    class="mb-1 flex items-center gap-2 text-sm font-medium text-primary"
                >
                    <CalendarDays class="size-4" aria-hidden="true" />
                    <span>{{ monthLabel }}</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    Dashboard
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                    Lihat pola pengeluaran, periksa transaksi, dan jaga
                    budgetmu.
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="min-h-11 w-full sm:w-auto"
                :disabled="refreshing"
                @click="loadAll"
            >
                <Spinner v-if="refreshing" class="size-4" />
                <RefreshCw v-else class="size-4" aria-hidden="true" />
                {{ refreshing ? 'Memperbarui…' : 'Perbarui data' }}
            </Button>
        </header>

        <h1 class="sr-only lg:hidden">Dashboard</h1>

        <div
            class="hidden items-center justify-between md:flex lg:hidden"
            aria-label="Kontrol dashboard"
        >
            <SidebarTrigger />
            <Button
                type="button"
                variant="outline"
                size="icon"
                class="size-11"
                :aria-label="refreshing ? 'Memperbarui data' : 'Perbarui data'"
                :disabled="refreshing"
                @click="loadAll"
            >
                <Spinner v-if="refreshing" class="size-4" />
                <RefreshCw v-else class="size-4" aria-hidden="true" />
            </Button>
        </div>

        <section
            v-if="error"
            aria-live="assertive"
            class="flex flex-col gap-3 rounded-xl bg-red-50 p-4 text-sm text-red-800 sm:flex-row sm:items-start dark:bg-red-950 dark:text-red-100"
        >
            <AlertCircle class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <h2 class="font-semibold">Data belum berhasil dimuat</h2>
                <p class="mt-1">{{ error }}</p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="min-h-11 border-red-300 bg-transparent hover:bg-red-100 dark:border-red-800 dark:hover:bg-red-900"
                @click="loadAll"
            >
                Coba lagi
            </Button>
        </section>

        <div class="sticky top-0 z-20 -mx-1 bg-background px-1 py-2 md:hidden">
            <div class="flex items-center gap-1 min-[400px]:gap-2">
                <SidebarTrigger class="shrink-0" />
                <div
                    role="tablist"
                    aria-label="Bagian dashboard"
                    class="grid min-w-0 flex-1 grid-cols-3 rounded-lg border bg-muted/40 p-1"
                >
                    <button
                        v-for="(tab, index) in mobileTabs"
                        :id="`mobile-tab-${tab.id}`"
                        ref="mobileTabButtons"
                        :key="tab.id"
                        type="button"
                        role="tab"
                        :aria-selected="activeMobileTab === tab.id"
                        :aria-controls="`mobile-panel-${tab.id}`"
                        :tabindex="activeMobileTab === tab.id ? 0 : -1"
                        class="min-h-11 min-w-0 rounded-md border px-0.5 text-xs font-medium transition-colors duration-150 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 motion-reduce:transition-none min-[400px]:px-1 min-[400px]:text-sm"
                        :class="
                            activeMobileTab === tab.id
                                ? 'border-border bg-background text-foreground'
                                : 'border-transparent text-muted-foreground hover:bg-background/60 hover:text-foreground'
                        "
                        @click="activateMobileTab(index)"
                        @keydown="handleMobileTabKeydown($event, index)"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    class="size-11 shrink-0"
                    :aria-label="
                        refreshing ? 'Memperbarui data' : 'Perbarui data'
                    "
                    :disabled="refreshing"
                    @click="loadAll"
                >
                    <Spinner v-if="refreshing" class="size-4" />
                    <RefreshCw v-else class="size-4" aria-hidden="true" />
                </Button>
            </div>
        </div>

        <div v-if="loading" aria-label="Memuat dashboard" class="space-y-4">
            <Skeleton class="h-48 rounded-xl" />
            <div class="grid gap-4 xl:grid-cols-[1.55fr_1fr]">
                <Skeleton class="h-80 rounded-xl" />
                <Skeleton class="h-80 rounded-xl" />
            </div>
            <span class="sr-only" role="status">Memuat data dashboard…</span>
        </div>

        <template v-else>
            <div
                id="mobile-panel-summary"
                :role="isMobileDashboard ? 'tabpanel' : undefined"
                :aria-labelledby="
                    isMobileDashboard ? 'mobile-tab-summary' : undefined
                "
                :aria-hidden="
                    isMobileDashboard
                        ? activeMobileTab !== 'summary'
                        : undefined
                "
                :tabindex="isMobileDashboard ? 0 : undefined"
                :class="
                    activeMobileTab === 'summary'
                        ? 'space-y-6 md:contents'
                        : 'hidden md:contents'
                "
            >
                <section
                    aria-labelledby="overview-title"
                    :aria-busy="periodLoading"
                    class="overflow-hidden rounded-xl border bg-card"
                >
                    <div
                        class="flex min-w-0 items-center justify-between gap-2 border-b px-4 py-4 min-[400px]:gap-3 sm:px-5"
                    >
                        <div class="min-w-0">
                            <h2
                                id="overview-title"
                                class="min-w-0 text-sm leading-tight font-semibold min-[400px]:text-base"
                                aria-live="polite"
                            >
                                {{ summaryTitle }}
                            </h2>

                            <p
                                class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                            >
                                Berdasarkan transaksi yang tercatat di DuitBot.
                            </p>
                        </div>

                        <div
                            class="inline-flex h-11 shrink-0 items-stretch rounded-md border bg-background"
                            role="group"
                            aria-label="Navigasi bulan laporan"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-11 rounded-l-md rounded-r-none border-r focus-visible:z-10"
                                :disabled="!canShowPreviousMonth"
                                :aria-label="`Lihat bulan sebelumnya, ${previousMonthLabel}`"
                                @click="showPreviousMonth"
                            >
                                <ChevronLeft
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </Button>

                            <Select
                                :model-value="selectedMonth"
                                :disabled="periodLoading"
                                @update:model-value="selectPeriod"
                            >
                                <SelectTrigger
                                    aria-label="Pilih bulan dan tahun laporan"
                                    class="h-11 w-24 min-w-24 rounded-none border-0 bg-transparent px-1.5 text-xs shadow-none focus:ring-0 focus-visible:z-10 focus-visible:ring-[3px] min-[400px]:w-auto min-[400px]:min-w-28 min-[400px]:px-2 min-[400px]:text-sm"
                                >
                                    <Spinner
                                        v-if="periodLoading"
                                        class="size-4"
                                    />
                                    <template v-else>
                                        <span class="min-[400px]:hidden">
                                            {{ compactMonthLabel }}
                                        </span>
                                        <span class="hidden min-[400px]:inline">
                                            {{ monthLabel }}
                                        </span>
                                    </template>
                                </SelectTrigger>
                                <SelectContent align="end">
                                    <SelectItem
                                        v-for="option in monthOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-11 rounded-l-none rounded-r-md border-l focus-visible:z-10"
                                :disabled="!canShowNextMonth"
                                :aria-label="`Lihat bulan berikutnya, ${nextMonthLabel}`"
                                @click="showNextMonth"
                            >
                                <ChevronRight
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </Button>
                        </div>

                        <p
                            v-if="periodLoading"
                            class="sr-only"
                            role="status"
                            aria-live="polite"
                        >
                            Memuat ringkasan {{ loadingMonthLabel }}
                        </p>
                    </div>

                    <dl class="grid md:grid-cols-[1.35fr_1fr_1fr]">
                        <div
                            class="bg-primary p-5 text-primary-foreground sm:p-6"
                        >
                            <dt
                                class="flex items-center justify-between gap-3 text-sm font-medium text-primary-foreground/85"
                            >
                                <span>Total pengeluaran</span>
                                <WalletCards
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </dt>
                            <dd class="mt-5 text-3xl font-bold tracking-tight">
                                {{ rupiah(summary?.total || 0) }}
                            </dd>
                            <p
                                class="mt-2 flex items-center gap-1.5 text-sm text-primary-foreground/85"
                            >
                                <TrendingUp
                                    v-if="
                                        (summary?.transaction_count ?? 0) > 0 &&
                                        (summary?.change_percent ?? 0) > 0
                                    "
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <TrendingDown
                                    v-else-if="
                                        (summary?.transaction_count ?? 0) > 0 &&
                                        (summary?.change_percent ?? 0) < 0
                                    "
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{ comparisonText }}
                            </p>
                        </div>

                        <div
                            class="border-t p-5 sm:p-6 md:border-t-0 md:border-l"
                        >
                            <dt
                                class="flex items-center justify-between gap-3 text-sm text-muted-foreground"
                            >
                                <span>Transaksi tercatat</span>
                                <ChartNoAxesCombined
                                    class="size-5 text-primary"
                                    aria-hidden="true"
                                />
                            </dt>
                            <dd class="mt-5 text-3xl font-bold tracking-tight">
                                {{ summary?.transaction_count ?? 0 }}
                            </dd>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ transactionPeriodText }}
                            </p>
                        </div>

                        <div
                            class="border-t p-5 sm:p-6 md:border-t-0 md:border-l"
                        >
                            <dt
                                class="flex items-center justify-between gap-3 text-sm text-muted-foreground"
                            >
                                <span>Sisa budget</span>
                                <Target
                                    class="size-5 text-primary"
                                    aria-hidden="true"
                                />
                            </dt>
                            <dd class="mt-5 text-3xl font-bold tracking-tight">
                                {{ rupiah(budgetRemaining) }}
                            </dd>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ budgetPeriodText }}
                            </p>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="
                        spendingNarrative || projection || categoryTrends.length
                    "
                    aria-labelledby="insight-title"
                    aria-live="polite"
                    class="rounded-xl border border-primary/20 bg-primary/[0.03] p-5 sm:p-6 dark:bg-primary/[0.06]"
                >
                    <h2
                        id="insight-title"
                        class="flex items-center gap-2 text-sm font-semibold"
                    >
                        <span
                            class="grid size-7 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"
                            aria-hidden="true"
                        >
                            <Lightbulb class="size-4" />
                        </span>
                        Insight bulan ini
                    </h2>

                    <p
                        v-if="spendingNarrative"
                        class="mt-3 text-sm leading-relaxed text-foreground"
                    >
                        {{ spendingNarrative }}
                    </p>

                    <p
                        v-if="projection"
                        class="mt-2 flex items-start gap-1.5 text-sm leading-relaxed"
                        :class="
                            projection.overBudget
                                ? 'text-red-700 dark:text-red-300'
                                : 'text-muted-foreground'
                        "
                    >
                        <AlertCircle
                            v-if="projection.overBudget"
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            Jika pola ini bertahan, akhir bulan diperkirakan
                            <strong>{{ rupiah(projection.amount) }}</strong>
                            <template v-if="projection.overBudget">
                                — melebihi budget total
                                {{ rupiah(budgetTotal) }}.
                            </template>
                            <template v-else-if="budgetTotal > 0">
                                — masih dalam budget total
                                {{ rupiah(budgetTotal) }}.
                            </template>
                            <template v-else>.</template>
                            <span class="text-muted-foreground">
                                Sisa {{ projection.remainingDays }} hari.
                            </span>
                        </span>
                    </p>

                    <ul
                        v-if="categoryTrends.length"
                        class="mt-4 flex flex-wrap gap-2"
                    >
                        <li
                            v-for="trend in categoryTrends"
                            :key="trend.category"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
                            :class="
                                trend.direction === 'down'
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                    : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                            "
                        >
                            <TrendingDown
                                v-if="trend.direction === 'down'"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            <TrendingUp
                                v-else
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{ categoryTrendText(trend) }}
                        </li>
                    </ul>
                </section>

                <section
                    aria-label="Grafik pengeluaran"
                    class="grid gap-4 xl:grid-cols-[1.55fr_1fr]"
                >
                    <article
                        class="min-w-0 overflow-hidden rounded-xl border bg-card p-5 sm:p-6"
                    >
                        <h2 class="font-semibold">Pengeluaran harian</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Perubahan pengeluaran sepanjang {{ monthLabel }}.
                        </p>
                        <div
                            ref="dailyChartContainer"
                            class="relative mt-5 h-64 max-w-full min-w-0 overflow-hidden sm:h-72"
                        >
                            <canvas
                                v-show="hasDailyData"
                                ref="dailyCanvas"
                                class="block max-w-full"
                                role="img"
                                :aria-label="`Grafik pengeluaran harian untuk ${monthLabel}`"
                            >
                                Grafik pengeluaran harian untuk
                                {{ monthLabel }}.
                            </canvas>
                            <div
                                v-if="!hasDailyData"
                                class="absolute inset-0 grid place-items-center rounded-lg bg-muted/45 p-6 text-center"
                            >
                                <div>
                                    <ChartNoAxesCombined
                                        class="mx-auto size-7 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <p class="mt-3 font-medium">
                                        Belum ada transaksi pada
                                        {{ monthLabel }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ emptyPeriodHelp }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article
                        class="min-w-0 overflow-hidden rounded-xl border bg-card p-5 sm:p-6"
                    >
                        <h2 class="font-semibold">Sebaran kategori</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Kategori yang paling banyak menyerap pengeluaran.
                        </p>
                        <div
                            ref="categoryChartContainer"
                            class="relative mt-5 h-64 max-w-full min-w-0 overflow-hidden sm:h-72"
                        >
                            <canvas
                                v-show="hasCategoryData"
                                ref="categoryCanvas"
                                class="block max-w-full"
                                role="img"
                                :aria-label="`Grafik sebaran kategori untuk ${monthLabel}`"
                            >
                                Grafik sebaran kategori untuk {{ monthLabel }}.
                            </canvas>
                            <div
                                v-if="!hasCategoryData"
                                class="absolute inset-0 grid place-items-center rounded-lg bg-muted/45 p-6 text-center"
                            >
                                <div>
                                    <Target
                                        class="mx-auto size-7 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <p class="mt-3 font-medium">
                                        Belum ada kategori pada {{ monthLabel }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ emptyPeriodHelp }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                </section>
            </div>

            <div
                id="mobile-panel-transactions"
                :role="isMobileDashboard ? 'tabpanel' : undefined"
                :aria-labelledby="
                    isMobileDashboard ? 'mobile-tab-transactions' : undefined
                "
                :aria-hidden="
                    isMobileDashboard
                        ? activeMobileTab !== 'transactions'
                        : undefined
                "
                :tabindex="isMobileDashboard ? 0 : undefined"
                :class="
                    activeMobileTab === 'transactions'
                        ? 'block md:contents'
                        : 'hidden md:contents'
                "
            >
                <section
                    aria-labelledby="transactions-title"
                    class="overflow-hidden rounded-xl border bg-card"
                >
                    <div class="border-b p-5 sm:p-6">
                        <div
                            class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <h2
                                    id="transactions-title"
                                    class="font-semibold"
                                >
                                    Riwayat transaksi
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Cari dan perbaiki catatan yang masuk dari
                                    Telegram.
                                </p>
                            </div>
                            <Button
                                v-if="hasActiveFilters"
                                type="button"
                                variant="ghost"
                                class="min-h-11 self-start px-0 text-primary hover:bg-transparent hover:text-primary/80 sm:px-3"
                                @click="resetFilters"
                            >
                                Hapus semua filter
                            </Button>
                        </div>

                        <form
                            class="mt-4 grid gap-3 min-[440px]:grid-cols-2 xl:grid-cols-[1.5fr_1fr_1fr_1fr_auto] xl:items-end"
                            @submit.prevent="applyFilters"
                        >
                            <div
                                class="space-y-2 min-[440px]:col-span-2 xl:col-span-1"
                            >
                                <Label for="transaction-search"
                                    >Cari transaksi</Label
                                >
                                <div class="relative">
                                    <Search
                                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        id="transaction-search"
                                        v-model="search"
                                        class="h-11 pl-9"
                                        placeholder="Contoh: makan siang"
                                    />
                                </div>
                            </div>

                            <div
                                class="space-y-2 min-[440px]:col-span-2 sm:col-span-1"
                            >
                                <Label for="transaction-category"
                                    >Kategori</Label
                                >
                                <Select v-model="categorySelectValue">
                                    <SelectTrigger
                                        id="transaction-category"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            placeholder="Semua kategori"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua kategori
                                        </SelectItem>
                                        <SelectItem
                                            v-for="item in categories"
                                            :key="item"
                                            :value="item"
                                        >
                                            {{ item }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label for="transaction-from"
                                    >Dari tanggal</Label
                                >
                                <Input
                                    id="transaction-from"
                                    v-model="from"
                                    type="date"
                                    lang="id-ID"
                                    :max="to || undefined"
                                    class="h-11"
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="transaction-to"
                                    >Sampai tanggal</Label
                                >
                                <Input
                                    id="transaction-to"
                                    v-model="to"
                                    type="date"
                                    lang="id-ID"
                                    :min="from || undefined"
                                    class="h-11"
                                />
                            </div>

                            <Button
                                type="submit"
                                class="min-h-11 w-full min-[440px]:col-span-2 sm:col-span-1 xl:col-span-1 xl:w-auto"
                                :disabled="transactionLoading"
                            >
                                <Spinner
                                    v-if="transactionLoading"
                                    class="size-4"
                                />
                                <Search
                                    v-else
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{
                                    transactionLoading ? 'Mencari…' : 'Terapkan'
                                }}
                            </Button>
                        </form>
                    </div>

                    <div
                        v-if="transactionLoading"
                        class="divide-y"
                        aria-live="polite"
                    >
                        <div
                            v-for="item in 5"
                            :key="item"
                            class="flex items-center gap-3 p-4 sm:px-6"
                        >
                            <Skeleton class="size-11 rounded-lg" />
                            <div class="flex-1 space-y-2">
                                <Skeleton class="h-4 w-1/3 rounded" />
                                <Skeleton class="h-3 w-1/4 rounded" />
                            </div>
                            <Skeleton class="h-4 w-20 rounded" />
                        </div>
                        <span class="sr-only" role="status">
                            Memuat transaksi…
                        </span>
                    </div>

                    <div v-else class="divide-y">
                        <div
                            v-if="!transactions.length"
                            class="flex flex-col items-center px-5 py-12 text-center sm:px-6"
                        >
                            <span
                                class="grid size-12 place-items-center rounded-xl bg-muted text-muted-foreground"
                            >
                                <Inbox class="size-6" aria-hidden="true" />
                            </span>
                            <h3 class="mt-4 font-semibold">
                                {{
                                    hasActiveFilters
                                        ? 'Transaksi tidak ditemukan'
                                        : 'Belum ada transaksi'
                                }}
                            </h3>
                            <p
                                class="mt-1 max-w-md text-sm text-muted-foreground"
                            >
                                {{
                                    hasActiveFilters
                                        ? 'Coba ubah kata pencarian, kategori, atau rentang tanggal.'
                                        : 'Kirim pengeluaran ke bot Telegram, misalnya “Makan siang 25rb”.'
                                }}
                            </p>
                            <Button
                                v-if="hasActiveFilters"
                                type="button"
                                variant="outline"
                                class="mt-5 min-h-11"
                                @click="resetFilters"
                            >
                                Hapus semua filter
                            </Button>
                        </div>

                        <article
                            v-for="tx in transactions"
                            :key="tx.id"
                            class="flex items-start gap-3 p-4 transition-colors hover:bg-muted/40 active:bg-muted/60 sm:grid sm:grid-cols-[auto_minmax(0,1fr)_auto_auto] sm:items-center sm:px-6"
                        >
                            <div
                                class="grid size-11 shrink-0 place-items-center rounded-lg bg-primary/10 text-lg"
                                aria-hidden="true"
                            >
                                {{ categoryEmoji(tx.category) }}
                            </div>

                            <div class="min-w-0 flex-1 sm:contents">
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate font-medium">
                                        {{ tx.description }}
                                    </h3>
                                    <p
                                        class="mt-0.5 text-sm text-muted-foreground"
                                    >
                                        {{ tx.category }} ·
                                        {{
                                            new Date(
                                                tx.occurred_at,
                                            ).toLocaleString('id-ID', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })
                                        }}
                                    </p>
                                </div>

                                <div
                                    class="mt-3 flex items-center justify-between gap-2 sm:contents"
                                >
                                    <strong
                                        class="text-base tabular-nums sm:min-w-28 sm:text-right sm:text-sm"
                                    >
                                        {{ rupiah(tx.amount) }}
                                    </strong>

                                    <div
                                        class="flex shrink-0 justify-end gap-1 sm:col-start-4"
                                    >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-11 text-muted-foreground hover:bg-primary/10 hover:text-primary focus-visible:text-primary"
                                            :aria-label="`Ubah transaksi ${tx.description}`"
                                            @click="beginEdit(tx)"
                                        >
                                            <Pencil
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-11 text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:text-destructive"
                                            :aria-label="`Hapus transaksi ${tx.description}`"
                                            @click="removeTx(tx)"
                                        >
                                            <Trash2
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <footer
                        v-if="pagination.total > 0"
                        class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                    >
                        <p class="text-sm text-muted-foreground">
                            Menampilkan {{ pagination.from }}–{{
                                pagination.to
                            }}
                            dari {{ pagination.total }} transaksi
                        </p>
                        <nav
                            class="flex items-center justify-between gap-2 sm:justify-end"
                            aria-label="Halaman transaksi"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                class="min-h-11 min-w-11"
                                :disabled="
                                    pagination.current_page === 1 ||
                                    transactionLoading
                                "
                                @click="goToPage(pagination.current_page - 1)"
                            >
                                <ChevronLeft
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <span class="hidden sm:inline">Sebelumnya</span>
                            </Button>
                            <span class="min-w-16 text-center text-sm">
                                {{ pagination.current_page }} dari
                                {{ pagination.last_page }}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                class="min-h-11 min-w-11"
                                :disabled="
                                    pagination.current_page ===
                                        pagination.last_page ||
                                    transactionLoading
                                "
                                @click="goToPage(pagination.current_page + 1)"
                            >
                                <span class="hidden sm:inline">Berikutnya</span>
                                <ChevronRight
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </Button>
                        </nav>
                    </footer>
                </section>
            </div>

            <div
                id="mobile-panel-budget"
                :role="isMobileDashboard ? 'tabpanel' : undefined"
                :aria-labelledby="
                    isMobileDashboard ? 'mobile-tab-budget' : undefined
                "
                :aria-hidden="
                    isMobileDashboard ? activeMobileTab !== 'budget' : undefined
                "
                :tabindex="isMobileDashboard ? 0 : undefined"
                :class="
                    activeMobileTab === 'budget'
                        ? 'block md:contents'
                        : 'hidden md:contents'
                "
            >
                <section
                    aria-labelledby="budget-title"
                    class="overflow-hidden rounded-xl border bg-card"
                >
                    <div class="border-b px-5 py-4 sm:px-6">
                        <h2 id="budget-title" class="font-semibold">
                            Budget per kategori
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            DuitBot mengirim peringatan Telegram saat penggunaan
                            mencapai 80% dan 100%.
                        </p>
                    </div>

                    <div
                        class="grid lg:grid-cols-[minmax(280px,0.75fr)_1.25fr]"
                    >
                        <form
                            class="space-y-4 bg-muted/35 p-5 sm:p-6"
                            novalidate
                            @submit.prevent="saveBudget"
                        >
                            <div class="space-y-2">
                                <Label for="budget-category">Kategori</Label>
                                <Select v-model="budgetCategory">
                                    <SelectTrigger
                                        id="budget-category"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            placeholder="Pilih kategori"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="item in categories"
                                            :key="item"
                                            :value="item"
                                        >
                                            {{ item }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label for="budget-limit">Batas bulanan</Label>
                                <CurrencyInput
                                    id="budget-limit"
                                    v-model="budgetLimit"
                                    aria-describedby="budget-limit-hint budget-limit-error"
                                    :aria-invalid="Boolean(budgetLimitError)"
                                    @update:model-value="budgetLimitError = ''"
                                />
                                <p
                                    id="budget-limit-hint"
                                    class="text-xs text-muted-foreground"
                                >
                                    Gunakan angka saja, tanpa titik atau koma.
                                </p>
                                <InputError
                                    id="budget-limit-error"
                                    role="alert"
                                    :message="budgetLimitError"
                                />
                            </div>

                            <Button
                                type="submit"
                                class="min-h-11 w-full"
                                :disabled="savingBudget || !budgetCategory"
                            >
                                <Spinner v-if="savingBudget" class="size-4" />
                                {{
                                    savingBudget
                                        ? 'Menyimpan…'
                                        : 'Simpan budget'
                                }}
                            </Button>
                        </form>

                        <div class="p-5 sm:p-6">
                            <div
                                v-if="budgets.length"
                                class="grid gap-3 md:grid-cols-2"
                            >
                                <article
                                    v-for="item in budgets"
                                    :key="item.id"
                                    class="rounded-xl border p-4"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <h3 class="font-semibold">
                                                {{ item.category }}
                                            </h3>
                                            <p
                                                class="mt-1 text-sm text-muted-foreground"
                                            >
                                                {{ rupiah(item.spent) }} dari
                                                {{ rupiah(item.monthly_limit) }}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-11 shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            :aria-label="`Hapus budget ${item.category}`"
                                            @click="removeBudget(item)"
                                        >
                                            <Trash2
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </div>

                                    <div
                                        class="mt-4 h-2 overflow-hidden rounded-full bg-muted"
                                        role="progressbar"
                                        :aria-label="`Pemakaian budget ${item.category}`"
                                        :aria-valuenow="
                                            Math.min(item.percentage, 100)
                                        "
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                    >
                                        <div
                                            class="h-full rounded-full transition-[width] duration-200 motion-reduce:transition-none"
                                            :class="
                                                item.percentage >= 100
                                                    ? 'bg-red-600'
                                                    : item.percentage >= 80
                                                      ? 'bg-amber-600'
                                                      : 'bg-primary'
                                            "
                                            :style="{
                                                width: `${Math.min(item.percentage, 100)}%`,
                                            }"
                                        />
                                    </div>
                                    <p
                                        class="mt-2 flex items-center justify-between gap-2 text-sm"
                                    >
                                        <span class="text-muted-foreground">
                                            {{
                                                item.percentage >= 100
                                                    ? 'Batas terlampaui'
                                                    : item.percentage >= 80
                                                      ? 'Mendekati batas'
                                                      : 'Masih aman'
                                            }}
                                        </span>
                                        <strong>{{ item.percentage }}%</strong>
                                    </p>
                                </article>
                            </div>

                            <div
                                v-else
                                class="grid min-h-48 place-items-center rounded-xl border border-dashed p-6 text-center"
                            >
                                <div>
                                    <Target
                                        class="mx-auto size-7 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <h3 class="mt-3 font-semibold">
                                        Belum ada budget
                                    </h3>
                                    <p
                                        class="mt-1 max-w-sm text-sm text-muted-foreground"
                                    >
                                        Pilih kategori dan tentukan batas
                                        bulanan untuk mulai memantau
                                        pengeluaran.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </template>
    </main>

    <Dialog :open="Boolean(editing)" @update:open="setEditDialog">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Ubah transaksi</DialogTitle>
                <DialogDescription>
                    Perbaiki detail transaksi. Ringkasan dan budget akan
                    diperbarui otomatis setelah disimpan.
                </DialogDescription>
            </DialogHeader>

            <form
                id="edit-transaction-form"
                class="space-y-4"
                novalidate
                @submit.prevent="saveEdit"
            >
                <div class="space-y-2">
                    <Label for="edit-description">Deskripsi</Label>
                    <Input
                        id="edit-description"
                        v-model="editDescription"
                        maxlength="255"
                        aria-describedby="edit-description-error"
                        :aria-invalid="Boolean(editDescriptionError)"
                        class="h-11"
                        @update:model-value="editDescriptionError = ''"
                    />
                    <InputError
                        id="edit-description-error"
                        role="alert"
                        :message="editDescriptionError"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="edit-amount">Nominal</Label>
                    <CurrencyInput
                        id="edit-amount"
                        v-model="editAmount"
                        aria-describedby="edit-amount-error"
                        :aria-invalid="Boolean(editAmountError)"
                        @update:model-value="editAmountError = ''"
                    />
                    <InputError
                        id="edit-amount-error"
                        role="alert"
                        :message="editAmountError"
                    />
                </div>
                <div class="space-y-2">
                    <Label for="edit-category">Kategori</Label>
                    <Select v-model="editCategory">
                        <SelectTrigger id="edit-category" class="w-full">
                            <SelectValue placeholder="Pilih kategori" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="item in categories"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </form>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :disabled="savingTransaction"
                    @click="setEditDialog(false)"
                >
                    Batal
                </Button>
                <Button
                    type="submit"
                    form="edit-transaction-form"
                    class="min-h-11"
                    :disabled="savingTransaction"
                >
                    <Spinner v-if="savingTransaction" class="size-4" />
                    {{ savingTransaction ? 'Menyimpan…' : 'Simpan perubahan' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
