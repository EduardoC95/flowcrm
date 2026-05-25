<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(route('tenant.store'));
};
</script>

<template>
    <Head title="Criar tenant" />

    <AuthLayout title="Criar workspace" description="Define o primeiro tenant para começares a usar o FlowCRM.">
        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Nome do tenant</Label>
                <Input id="name" v-model="form.name" type="text" required autofocus autocomplete="organization" placeholder="A minha empresa" />
                <InputError :message="form.errors.name" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">Criar tenant</Button>
        </form>
    </AuthLayout>
</template>
