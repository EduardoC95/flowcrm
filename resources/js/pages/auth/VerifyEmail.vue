<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <AuthLayout title="Verificar email" description="Verifique o endereço de email através da ligação que acabámos de enviar.">
        <Head title="Verificação de email" />

        <div v-if="status === 'verification-link-sent'" class="mb-4 text-center text-sm font-medium text-green-600">
            Foi enviada uma nova ligação de verificação para o endereço de email indicado no registo.
        </div>

        <form @submit.prevent="submit" class="space-y-6 text-center">
            <Button :disabled="form.processing" variant="secondary">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Reenviar email de verificação
            </Button>

            <TextLink :href="route('logout')" method="post" as="button" class="mx-auto block text-sm">Terminar sessão</TextLink>
        </form>
    </AuthLayout>
</template>
