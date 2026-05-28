<script setup lang="ts">
import DealTimeline from '@/components/deals/DealTimeline.vue';
import QuickActivityForm from '@/components/deals/QuickActivityForm.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Bell, CheckCircle2, Download, Mail, Paperclip, Pencil, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Option {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
    position?: string | null;
}

interface Stage {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    is_won: boolean;
    is_lost: boolean;
}

interface ActivityLog {
    id: number;
    action: string;
    description: string | null;
    created_at: string | null;
    user: Option | null;
}

interface CalendarEvent {
    id: number;
    title: string;
    type: string;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    location: string | null;
}

interface DealProposal {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    status: 'draft' | 'sent';
    created_at: string | null;
    sent_at: string | null;
    recipient_email: string | null;
    email_subject: string | null;
    uploader: Option | null;
    sender: Option | null;
    download_url: string;
}

interface DealFollowUp {
    id: number;
    status: string;
    next_send_at: string | null;
    last_sent_at: string | null;
    sent_count: number;
    cancelled_at: string | null;
    replied_at: string | null;
}

interface DealFollowUpEmail {
    id: number;
    recipient_email: string;
    subject: string;
    body: string;
    sent_at: string | null;
    template: Option | null;
    sender: Option | null;
}

interface ProductOption {
    id: number;
    name: string;
    sku: string | null;
    unit_price: number;
}

interface DealProduct {
    id: number;
    quantity: number;
    unit_price: number;
    total: number;
    product: ProductOption | null;
}

interface TimelineItem {
    id: string;
    source_type: string;
    type: string;
    title: string;
    description: string | null;
    occurred_at: string | null;
    user_name: string | null;
    badge_label: string;
    icon: string;
    metadata: Record<string, unknown>;
    details: Record<string, unknown>;
}

interface AISuggestion {
    id: number;
    title: string;
    reason: string;
    suggested_action: string;
    priority: string;
    score: number;
    url: string;
}

const props = defineProps<{
    deal: {
        id: number;
        title: string;
        value: number;
        probability: number;
        expected_close_date: string | null;
        priority: string | null;
        description: string | null;
        last_activity_at: string | null;
        created_at: string | null;
        updated_at: string | null;
        products_total: number;
        entity: Option | null;
        person: Option | null;
        owner: Option | null;
        stage: Stage | null;
        deal_products: DealProduct[];
        proposals: DealProposal[];
        follow_up: DealFollowUp | null;
        follow_up_emails: DealFollowUpEmail[];
        calendar_events: CalendarEvent[];
        activity_logs: ActivityLog[];
        ai_suggestions: AISuggestion[];
    };
    can: {
        update: boolean;
        delete: boolean;
        manageProposals: boolean;
        manageFollowUp: boolean;
        manageProducts: boolean;
        createQuickActivities: boolean;
    };
    productOptions: ProductOption[];
    activityOwners: Option[];
    timeline: TimelineItem[];
}>();

const page = usePage<SharedData>();
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Negócios', href: '/deals' },
    { title: props.deal.title, href: `/deals/${props.deal.id}` },
];

const priorityLabels: Record<string, string> = {
    low: 'Baixa',
    medium: 'Média',
    high: 'Alta',
    urgent: 'Urgente',
};

const showSendModal = ref(false);
const selectedProposal = ref<DealProposal | null>(null);
const previewLoading = ref(false);
const expandedFollowUpEmailId = ref<number | null>(null);

const uploadForm = useForm<{ proposal: File | null }>({
    proposal: null,
});

const sendForm = useForm({
    recipient_email: '',
    email_subject: '',
    email_body: '',
});

const productForm = useForm({
    product_id: '',
    quantity: '1',
    unit_price: '',
});

const editingProductId = ref<number | null>(null);
const productEditForm = useForm({
    quantity: '1',
    unit_price: '0',
});

const latestProposal = computed(() => props.deal.proposals[0] ?? null);
const sentProposals = computed(() => props.deal.proposals.filter((proposal) => proposal.status === 'sent'));

const money = (value: number) =>
    new Intl.NumberFormat('pt-PT', {
        style: 'currency',
        currency: 'EUR',
    }).format(value);

const destroy = () => {
    if (confirm(`Apagar o negócio "${props.deal.title}"?`)) {
        router.delete(`/deals/${props.deal.id}`);
    }
};

const selectProposalFile = (event: Event) => {
    uploadForm.proposal = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const formatBytes = (bytes: number) => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const uploadProposal = () => {
    uploadForm.post(`/deals/${props.deal.id}/proposals`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => uploadForm.reset(),
    });
};

const removeProposal = (proposal: DealProposal) => {
    if (confirm(`Remover a proposta "${proposal.original_name}"?`)) {
        router.delete(`/deals/${props.deal.id}/proposals/${proposal.id}`, {
            preserveScroll: true,
        });
    }
};

const openSendModal = async (proposal: DealProposal) => {
    selectedProposal.value = proposal;
    showSendModal.value = true;
    previewLoading.value = true;
    sendForm.clearErrors();

    try {
        const response = await fetch(`/deals/${props.deal.id}/proposals/${proposal.id}/preview-email`, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('preview failed');
        }

        const data = await response.json();
        sendForm.recipient_email = data.recipient_email ?? '';
        sendForm.email_subject = data.email_subject ?? '';
        sendForm.email_body = data.email_body ?? '';
    } finally {
        previewLoading.value = false;
    }
};

const closeSendModal = () => {
    showSendModal.value = false;
    selectedProposal.value = null;
    sendForm.reset();
    sendForm.clearErrors();
};

const sendProposal = () => {
    if (!selectedProposal.value) {
        return;
    }

    sendForm.post(`/deals/${props.deal.id}/proposals/${selectedProposal.value.id}/send`, {
        preserveScroll: true,
        onSuccess: closeSendModal,
    });
};

const cancelFollowUp = () => {
    if (confirm('Cancelar o follow-up automático deste negócio?')) {
        router.patch(
            `/deals/${props.deal.id}/follow-up/cancel`,
            { cancellation_reason: 'Cancelado manualmente pelo utilizador' },
            { preserveScroll: true },
        );
    }
};

const markClientReplied = () => {
    if (confirm('Marcar este follow-up como respondido pelo cliente?')) {
        router.patch(`/deals/${props.deal.id}/follow-up/client-replied`, {}, { preserveScroll: true });
    }
};

const addProduct = () => {
    productForm.post(`/deals/${props.deal.id}/products`, {
        preserveScroll: true,
        onSuccess: () => productForm.reset(),
    });
};

const startEditingProduct = (dealProduct: DealProduct) => {
    editingProductId.value = dealProduct.id;
    productEditForm.quantity = String(dealProduct.quantity);
    productEditForm.unit_price = String(dealProduct.unit_price);
    productEditForm.clearErrors();
};

const updateProduct = (dealProduct: DealProduct) => {
    productEditForm.patch(`/deals/${props.deal.id}/products/${dealProduct.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingProductId.value = null;
        },
    });
};

const removeProduct = (dealProduct: DealProduct) => {
    if (confirm(`Remover "${dealProduct.product?.name ?? 'produto'}" deste negócio?`)) {
        router.delete(`/deals/${props.deal.id}/products/${dealProduct.id}`, {
            preserveScroll: true,
        });
    }
};

const ignoreSuggestion = (suggestion: AISuggestion) => {
    router.patch(`/ai-suggestions/${suggestion.id}/ignore`, {}, { preserveScroll: true });
};

const convertSuggestion = (suggestion: AISuggestion) => {
    router.post(`/ai-suggestions/${suggestion.id}/convert-to-activity`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="deal.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div v-if="page.props.flash.success" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash.error" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ page.props.flash.error }}
            </div>

            <section class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                        <Link href="/deals">
                            <ArrowLeft class="size-4" />
                            Voltar
                        </Link>
                    </Button>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ deal.title }}</h1>
                    <p class="text-sm text-muted-foreground">Histórico, etapa, contactos e valores do negócio.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="can.update" as-child>
                        <Link :href="`/deals/${deal.id}/edit`">
                            <Pencil class="size-4" />
                            Editar
                        </Link>
                    </Button>
                    <Button v-if="can.delete" variant="destructive" @click="destroy">
                        <Trash2 class="size-4" />
                        Apagar
                    </Button>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium">
                            {{ deal.stage?.name ?? 'Sem etapa' }}
                        </span>
                        <span class="inline-flex rounded-full border px-2 py-1 text-xs font-medium">
                            {{ deal.priority ? priorityLabels[deal.priority] : 'Sem prioridade' }}
                        </span>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <div>
                            <p class="text-sm text-muted-foreground">Valor</p>
                            <p class="mt-1 text-2xl font-semibold">{{ money(deal.value) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Probabilidade</p>
                            <p class="mt-1 text-2xl font-semibold">{{ deal.probability }}%</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Data prevista</p>
                            <p class="mt-1 text-2xl font-semibold">{{ deal.expected_close_date ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <p class="text-sm font-medium">Descrição</p>
                        <p class="mt-2 whitespace-pre-line text-sm text-muted-foreground">{{ deal.description ?? 'Sem descrição registada.' }}</p>
                    </div>
                </section>

                <aside class="space-y-4">
                    <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="font-medium">Sugestões AI</h2>
                            <Link href="/ai-suggestions" class="text-sm text-primary hover:underline">Ver backlog</Link>
                        </div>
                        <div class="mt-4 space-y-3">
                            <div v-for="suggestion in deal.ai_suggestions" :key="suggestion.id" class="rounded-md border p-3 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <Link :href="suggestion.url" class="font-medium text-primary hover:underline">{{ suggestion.title }}</Link>
                                        <p class="mt-1 line-clamp-2 text-muted-foreground">{{ suggestion.reason }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">Score {{ suggestion.score }} · {{ suggestion.priority }}</p>
                                    </div>
                                </div>
                                <div v-if="can.update" class="mt-3 flex flex-wrap gap-2">
                                    <Button size="sm" variant="outline" @click="convertSuggestion(suggestion)">Converter</Button>
                                    <Button size="sm" variant="ghost" @click="ignoreSuggestion(suggestion)">Ignorar</Button>
                                </div>
                            </div>
                            <p v-if="deal.ai_suggestions.length === 0" class="text-sm text-muted-foreground">
                                Sem sugestões AI pendentes para este negócio.
                            </p>
                        </div>
                    </section>

                    <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <h2 class="font-medium">Relações</h2>
                        <div class="mt-4 space-y-3 text-sm">
                            <div>
                                <p class="text-muted-foreground">Entidade</p>
                                <Link v-if="deal.entity" :href="`/entities/${deal.entity.id}`" class="text-primary hover:underline">{{
                                    deal.entity.name
                                }}</Link>
                                <span v-else>Sem entidade</span>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Pessoa</p>
                                <Link v-if="deal.person" :href="`/people/${deal.person.id}`" class="text-primary hover:underline">{{
                                    deal.person.name
                                }}</Link>
                                <span v-else>Sem pessoa</span>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Responsável</p>
                                <span>{{ deal.owner?.name ?? '-' }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="font-medium">Proposta</h2>
                            <span v-if="latestProposal" class="rounded-full border px-2 py-1 text-xs font-medium">
                                {{ latestProposal.status === 'sent' ? 'Enviada' : 'Rascunho' }}
                            </span>
                        </div>

                        <div v-if="!latestProposal" class="mt-4 rounded-md border border-dashed p-4">
                            <p class="text-sm text-muted-foreground">Ainda não há proposta adicionada a este negócio.</p>
                            <form v-if="can.manageProposals" class="mt-4 space-y-3" @submit.prevent="uploadProposal">
                                <Input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" @input="selectProposalFile" />
                                <p v-if="uploadForm.errors.proposal" class="text-sm text-destructive">{{ uploadForm.errors.proposal }}</p>
                                <Button type="submit" :disabled="uploadForm.processing || !uploadForm.proposal">
                                    <Paperclip class="size-4" />
                                    Adicionar proposta
                                </Button>
                            </form>
                        </div>

                        <div v-else class="mt-4 space-y-4">
                            <div class="rounded-md border p-3 text-sm">
                                <p class="font-medium">{{ latestProposal.original_name }}</p>
                                <p class="mt-1 text-muted-foreground">
                                    {{ formatBytes(latestProposal.size) }} · carregada em {{ latestProposal.created_at ?? '-' }} por
                                    {{ latestProposal.uploader?.name ?? '-' }}
                                </p>
                                <p v-if="latestProposal.sent_at" class="mt-1 text-muted-foreground">
                                    Enviada em {{ latestProposal.sent_at }} por {{ latestProposal.sender?.name ?? '-' }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button as-child variant="outline" size="sm">
                                    <a :href="latestProposal.download_url">
                                        <Download class="size-4" />
                                        Download
                                    </a>
                                </Button>
                                <Button v-if="can.manageProposals" size="sm" @click="openSendModal(latestProposal)">
                                    <Mail class="size-4" />
                                    Enviar proposta ao cliente
                                </Button>
                                <Button
                                    v-if="can.manageProposals && latestProposal.status !== 'sent'"
                                    variant="outline"
                                    size="sm"
                                    @click="removeProposal(latestProposal)"
                                >
                                    <Trash2 class="size-4" />
                                    Remover
                                </Button>
                            </div>

                            <form v-if="can.manageProposals" class="space-y-3 border-t pt-4" @submit.prevent="uploadProposal">
                                <Label>Substituir/adicionar nova versão</Label>
                                <Input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" @input="selectProposalFile" />
                                <p v-if="uploadForm.errors.proposal" class="text-sm text-destructive">{{ uploadForm.errors.proposal }}</p>
                                <Button type="submit" variant="outline" size="sm" :disabled="uploadForm.processing || !uploadForm.proposal"
                                    >Adicionar ficheiro</Button
                                >
                            </form>
                        </div>
                    </section>

                    <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="font-medium">Follow-up automático</h2>
                            <span
                                v-if="deal.follow_up"
                                class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700"
                            >
                                Ativo
                            </span>
                        </div>

                        <div v-if="!deal.follow_up" class="mt-4 rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            Este negócio ainda não tem follow-up automático ativo.
                            <span v-if="deal.stage?.slug === 'follow-up'">
                                Move novamente o cartão para Follow Up se precisares de reiniciar o ciclo.</span
                            >
                        </div>

                        <div v-else class="mt-4 space-y-4 text-sm">
                            <div class="grid gap-3 rounded-md border p-3">
                                <div>
                                    <p class="text-muted-foreground">Próximo envio</p>
                                    <p class="font-medium">{{ deal.follow_up.next_send_at ?? '-' }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-muted-foreground">Último envio</p>
                                        <p>{{ deal.follow_up.last_sent_at ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-muted-foreground">Emails enviados</p>
                                        <p>{{ deal.follow_up.sent_count }}</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="can.manageFollowUp" class="flex flex-wrap gap-2">
                                <Button variant="outline" size="sm" @click="markClientReplied">
                                    <CheckCircle2 class="size-4" />
                                    Marcar como cliente respondeu
                                </Button>
                                <Button variant="outline" size="sm" @click="cancelFollowUp">
                                    <X class="size-4" />
                                    Cancelar follow-up
                                </Button>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>

            <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-medium">Produtos do negócio</h2>
                        <p class="text-sm text-muted-foreground">Total em produtos: {{ money(deal.products_total) }}</p>
                    </div>
                </div>

                <form
                    v-if="can.manageProducts"
                    class="mt-4 grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_120px_150px_auto]"
                    @submit.prevent="addProduct"
                >
                    <select v-model="productForm.product_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="">Selecionar produto</option>
                        <option v-for="product in productOptions" :key="product.id" :value="product.id">
                            {{ product.name }}{{ product.sku ? ` · ${product.sku}` : '' }}
                        </option>
                    </select>
                    <Input v-model="productForm.quantity" type="number" min="0.01" step="0.01" placeholder="Qtd." />
                    <Input v-model="productForm.unit_price" type="number" min="0" step="0.01" placeholder="Preço" />
                    <Button type="submit" :disabled="productForm.processing">Adicionar produto</Button>
                    <p v-if="productForm.errors.product_id" class="text-sm text-destructive md:col-span-4">{{ productForm.errors.product_id }}</p>
                    <p v-if="productForm.errors.quantity" class="text-sm text-destructive md:col-span-4">{{ productForm.errors.quantity }}</p>
                    <p v-if="productForm.errors.unit_price" class="text-sm text-destructive md:col-span-4">{{ productForm.errors.unit_price }}</p>
                </form>

                <div class="mt-4 overflow-x-auto">
                    <table v-if="deal.deal_products.length" class="w-full text-sm">
                        <thead class="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th class="px-4 py-3 font-medium">Produto</th>
                                <th class="px-4 py-3 font-medium">SKU</th>
                                <th class="px-4 py-3 font-medium">Quantidade</th>
                                <th class="px-4 py-3 font-medium">Preço unitário</th>
                                <th class="px-4 py-3 font-medium">Total</th>
                                <th class="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="dealProduct in deal.deal_products" :key="dealProduct.id" class="border-b last:border-0">
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="dealProduct.product"
                                        :href="`/products/${dealProduct.product.id}`"
                                        class="font-medium text-primary hover:underline"
                                    >
                                        {{ dealProduct.product.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ dealProduct.product?.sku ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <Input
                                        v-if="editingProductId === dealProduct.id"
                                        v-model="productEditForm.quantity"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        class="w-28"
                                    />
                                    <span v-else>{{ dealProduct.quantity }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Input
                                        v-if="editingProductId === dealProduct.id"
                                        v-model="productEditForm.unit_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-32"
                                    />
                                    <span v-else>{{ money(dealProduct.unit_price) }}</span>
                                </td>
                                <td class="px-4 py-3 font-medium">{{ money(dealProduct.total) }}</td>
                                <td class="px-4 py-3">
                                    <div v-if="can.manageProducts" class="flex justify-end gap-1">
                                        <Button
                                            v-if="editingProductId === dealProduct.id"
                                            variant="outline"
                                            size="sm"
                                            @click="updateProduct(dealProduct)"
                                        >
                                            Guardar
                                        </Button>
                                        <Button v-else variant="ghost" size="sm" @click="startEditingProduct(dealProduct)">Editar</Button>
                                        <Button variant="ghost" size="sm" @click="removeProduct(dealProduct)">Remover</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Ainda não há produtos associados a este negócio.
                    </p>
                </div>
            </section>

            <div class="grid gap-4 xl:grid-cols-[1fr_2fr]">
                <QuickActivityForm
                    :deal-id="deal.id"
                    :owners="activityOwners"
                    :can-create="can.createQuickActivities"
                    :default-owner-id="deal.owner?.id ?? null"
                    :default-priority="deal.priority"
                />
                <DealTimeline :deal-id="deal.id" :items="timeline" />
            </div>

            <div class="hidden">
                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Eventos/Atividades</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="event in deal.calendar_events" :key="event.id" class="rounded-md border p-3 text-sm">
                            <Link :href="`/calendar-events/${event.id}`" class="font-medium text-primary hover:underline">{{ event.title }}</Link>
                            <p class="text-muted-foreground">{{ event.starts_at ?? '-' }} · {{ event.location ?? 'Sem localização' }}</p>
                        </div>
                        <p v-if="deal.calendar_events.length === 0" class="text-sm text-muted-foreground">Ainda não há eventos associados.</p>
                    </div>
                </section>

                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Histórico/Logs</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="email in deal.follow_up_emails" :key="`follow-up-email-${email.id}`" class="rounded-md border p-3 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium">Email de follow-up enviado</p>
                                    <p class="text-muted-foreground">{{ email.sent_at ?? '-' }} · {{ email.recipient_email }}</p>
                                    <p class="text-muted-foreground">{{ email.subject }}</p>
                                    <p v-if="email.template" class="text-xs text-muted-foreground">Template: {{ email.template.name }}</p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="expandedFollowUpEmailId = expandedFollowUpEmailId === email.id ? null : email.id"
                                >
                                    <Bell class="size-4" />
                                </Button>
                            </div>
                            <p
                                v-if="expandedFollowUpEmailId === email.id"
                                class="mt-3 whitespace-pre-line rounded-md bg-muted p-3 text-muted-foreground"
                            >
                                {{ email.body }}
                            </p>
                        </div>
                        <div v-for="proposal in sentProposals" :key="`sent-${proposal.id}`" class="rounded-md border p-3 text-sm">
                            <p class="font-medium">Proposta enviada</p>
                            <p class="text-muted-foreground">{{ proposal.sent_at ?? '-' }} · {{ proposal.sender?.name ?? '-' }}</p>
                            <p class="text-muted-foreground">{{ proposal.recipient_email }} · {{ proposal.email_subject }}</p>
                        </div>
                        <div v-for="log in deal.activity_logs" :key="log.id" class="rounded-md border p-3 text-sm">
                            <p class="font-medium">{{ log.action }}</p>
                            <p class="text-muted-foreground">{{ log.description ?? '-' }}</p>
                            <p class="text-xs text-muted-foreground">{{ log.created_at }} · {{ log.user?.name ?? 'Sistema' }}</p>
                        </div>
                        <p v-if="deal.activity_logs.length === 0" class="text-sm text-muted-foreground">Ainda não há histórico registado.</p>
                    </div>
                </section>

                <section class="rounded-lg border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border">
                    <h2 class="font-medium">Cronologia e Atividades Rápidas</h2>
                    <div class="mt-4 space-y-3 text-sm text-muted-foreground">
                        <p>Última atividade: {{ deal.last_activity_at ?? '-' }}</p>
                        <Button as-child variant="outline" class="w-full justify-start">
                            <Link href="/calendar-events/create">Preparar evento</Link>
                        </Button>
                        <Button as-child variant="outline" class="w-full justify-start">
                            <Link href="/deals-board">Ver no pipeline</Link>
                        </Button>
                    </div>
                </section>
            </div>
        </div>

        <div v-if="showSendModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-2xl rounded-lg border bg-background p-5 shadow-lg">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold">Enviar proposta ao cliente</h2>
                        <p class="text-sm text-muted-foreground">{{ selectedProposal?.original_name }}</p>
                    </div>
                    <Button variant="ghost" size="icon" @click="closeSendModal">
                        <X class="size-4" />
                    </Button>
                </div>

                <div v-if="previewLoading" class="mt-5 rounded-md border p-4 text-sm text-muted-foreground">A preparar email...</div>

                <form v-else class="mt-5 space-y-4" @submit.prevent="sendProposal">
                    <div class="space-y-2">
                        <Label for="recipient_email">Destinatário</Label>
                        <Input id="recipient_email" v-model="sendForm.recipient_email" type="email" />
                        <p v-if="sendForm.errors.recipient_email" class="text-sm text-destructive">{{ sendForm.errors.recipient_email }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="email_subject">Assunto</Label>
                        <Input id="email_subject" v-model="sendForm.email_subject" />
                        <p v-if="sendForm.errors.email_subject" class="text-sm text-destructive">{{ sendForm.errors.email_subject }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="email_body">Mensagem</Label>
                        <textarea
                            id="email_body"
                            v-model="sendForm.email_body"
                            rows="9"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <p v-if="sendForm.errors.email_body" class="text-sm text-destructive">{{ sendForm.errors.email_body }}</p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="closeSendModal">Cancelar</Button>
                        <Button type="submit" :disabled="sendForm.processing">
                            <Mail class="size-4" />
                            Enviar
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
