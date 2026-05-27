<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeadFormRequest;
use App\Http\Requests\UpdateLeadFormRequest;
use App\Models\LeadForm;
use App\Models\LeadFormSubmission;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LeadFormController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', LeadForm::class);

        $forms = LeadForm::query()
            ->with('creator:id,name')
            ->withCount('submissions')
            ->latest()
            ->paginate(10)
            ->through(fn (LeadForm $form) => $this->formRow($form));

        return Inertia::render('lead-forms/Index', [
            'leadForms' => $forms,
            'can' => [
                'create' => $request->user()->can('create', LeadForm::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', LeadForm::class);

        return Inertia::render('lead-forms/Create', [
            'defaults' => [
                'fields' => $this->defaultFields(),
            ],
            'fieldTypes' => $this->fieldTypes(),
        ]);
    }

    public function store(StoreLeadFormRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $leadForm = LeadForm::create([
            ...$this->payload($request->validated()),
            'slug' => $this->resolveSlug($request->validated('slug') ?: $request->validated('name')),
            'created_by' => $request->user()->id,
        ]);

        $logger->log('lead_form.created', 'lead_forms', $leadForm->tenant_id, $leadForm, 'Formulário de lead criado.', [
            'name' => $leadForm->name,
            'slug' => $leadForm->slug,
        ]);

        return redirect()
            ->route('lead-forms.show', $leadForm)
            ->with('success', 'Formulário criado com sucesso.');
    }

    public function show(Request $request, LeadForm $leadForm): Response
    {
        Gate::authorize('view', $leadForm);

        $leadForm->load('creator:id,name');

        $submissions = $leadForm->submissions()
            ->with(['createdPerson:id,name,email', 'createdDeal:id,title'])
            ->latest('submitted_at')
            ->paginate(12)
            ->through(fn (LeadFormSubmission $submission) => [
                'id' => $submission->id,
                'payload' => $submission->payload,
                'name' => $submission->payload['name'] ?? null,
                'email' => $submission->payload['email'] ?? null,
                'source_url' => $submission->source_url,
                'ip_address' => $submission->ip_address,
                'user_agent' => $submission->user_agent,
                'captcha_passed' => $submission->captcha_passed,
                'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                'created_person' => $submission->createdPerson?->only(['id', 'name', 'email']),
                'created_deal' => $submission->createdDeal?->only(['id', 'title']),
            ]);

        return Inertia::render('lead-forms/Show', [
            'leadForm' => [
                ...$this->formRow($leadForm),
                'description' => $leadForm->description,
                'fields' => $leadForm->fields,
                'confirmation_message' => $leadForm->confirmation_message,
                'embed' => $this->embedData($leadForm),
                'creator' => $leadForm->creator?->only(['id', 'name']),
            ],
            'submissions' => $submissions,
            'can' => [
                'update' => $request->user()->can('update', $leadForm),
                'delete' => $request->user()->can('delete', $leadForm),
            ],
        ]);
    }

    public function edit(LeadForm $leadForm): Response
    {
        Gate::authorize('update', $leadForm);

        return Inertia::render('lead-forms/Edit', [
            'leadForm' => [
                'id' => $leadForm->id,
                'name' => $leadForm->name,
                'slug' => $leadForm->slug,
                'description' => $leadForm->description,
                'fields' => $leadForm->fields,
                'confirmation_message' => $leadForm->confirmation_message,
                'active' => $leadForm->active,
                'require_captcha' => $leadForm->require_captcha,
            ],
            'fieldTypes' => $this->fieldTypes(),
        ]);
    }

    public function update(UpdateLeadFormRequest $request, LeadForm $leadForm, ActivityLogger $logger): RedirectResponse
    {
        $leadForm->update([
            ...$this->payload($request->validated()),
            'slug' => $request->validated('slug') && $request->validated('slug') !== $leadForm->slug
                ? $this->resolveSlug($request->validated('slug'), $leadForm)
                : $leadForm->slug,
        ]);

        $logger->log('lead_form.updated', 'lead_forms', $leadForm->tenant_id, $leadForm, 'Formulário de lead atualizado.', [
            'changes' => $leadForm->getChanges(),
        ]);

        return redirect()
            ->route('lead-forms.show', $leadForm)
            ->with('success', 'Formulário atualizado com sucesso.');
    }

    public function destroy(LeadForm $leadForm, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('delete', $leadForm);

        $logger->log('lead_form.deleted', 'lead_forms', $leadForm->tenant_id, $leadForm, 'Formulário de lead apagado.', [
            'name' => $leadForm->name,
            'slug' => $leadForm->slug,
        ]);

        $leadForm->delete();

        return redirect()
            ->route('lead-forms.index')
            ->with('success', 'Formulário apagado com sucesso.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultFields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome', 'type' => LeadForm::FIELD_TEXT, 'required' => true, 'placeholder' => 'O seu nome'],
            ['key' => 'email', 'label' => 'Email', 'type' => LeadForm::FIELD_EMAIL, 'required' => true, 'placeholder' => 'email@empresa.pt'],
            ['key' => 'phone', 'label' => 'Telefone', 'type' => LeadForm::FIELD_PHONE, 'required' => false, 'placeholder' => '+351 ...'],
            ['key' => 'company', 'label' => 'Empresa', 'type' => LeadForm::FIELD_TEXT, 'required' => false, 'placeholder' => 'Nome da empresa'],
            ['key' => 'message', 'label' => 'Mensagem', 'type' => LeadForm::FIELD_TEXTAREA, 'required' => false, 'placeholder' => 'Como podemos ajudar?'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function fieldTypes(): array
    {
        return [
            ['value' => LeadForm::FIELD_TEXT, 'label' => 'Texto'],
            ['value' => LeadForm::FIELD_EMAIL, 'label' => 'Email'],
            ['value' => LeadForm::FIELD_PHONE, 'label' => 'Telefone'],
            ['value' => LeadForm::FIELD_TEXTAREA, 'label' => 'Texto longo'],
            ['value' => LeadForm::FIELD_SELECT, 'label' => 'Seleção'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'fields' => $this->normalizeFields($validated['fields']),
            'confirmation_message' => $validated['confirmation_message'] ?? null,
            'active' => (bool) ($validated['active'] ?? false),
            'require_captcha' => (bool) ($validated['require_captcha'] ?? false),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFields(array $fields): array
    {
        return collect($fields)
            ->map(fn (array $field) => [
                'key' => Str::slug((string) $field['key'], '_'),
                'label' => trim((string) $field['label']),
                'type' => $field['type'],
                'required' => (bool) ($field['required'] ?? false),
                'placeholder' => $field['placeholder'] ?? null,
                'options' => array_values(array_filter($field['options'] ?? [])),
            ])
            ->unique('key')
            ->values()
            ->all();
    }

    private function resolveSlug(string $value, ?LeadForm $ignore = null): string
    {
        $base = Str::slug($value) ?: 'formulario-lead';
        $slug = $base;
        $counter = 2;

        while (LeadForm::withoutGlobalScopes()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, string>
     */
    private function embedData(LeadForm $leadForm): array
    {
        $publicUrl = route('public.lead-forms.show', $leadForm->slug);

        return [
            'public_url' => $publicUrl,
            'iframe_embed_code' => sprintf('<iframe src="%s" width="100%%" height="700" style="border:0;"></iframe>', e($publicUrl)),
            'script_embed_code' => sprintf('<script src="%s/lead-form-widget.js" data-form="%s"></script>', rtrim(config('app.url'), '/'), e($leadForm->slug)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formRow(LeadForm $form): array
    {
        return [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'active' => $form->active,
            'require_captcha' => $form->require_captcha,
            'submissions_count' => $form->submissions_count ?? null,
            'created_at' => $form->created_at?->toDateTimeString(),
            'creator' => $form->creator?->only(['id', 'name']),
        ];
    }
}
