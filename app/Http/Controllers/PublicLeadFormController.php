<?php

namespace App\Http\Controllers;

use App\Models\LeadForm;
use App\Services\Captcha\CaptchaVerifier;
use App\Services\LeadFormSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicLeadFormController extends Controller
{
    public function show(string $slug): Response
    {
        $form = $this->activeForm($slug);

        return Inertia::render('public/lead-forms/Show', [
            'leadForm' => $this->publicForm($form),
            'submitted' => false,
        ]);
    }

    public function submit(
        Request $request,
        string $slug,
        CaptchaVerifier $captcha,
        LeadFormSubmissionService $submissions,
    ): Response {
        $form = $this->activeForm($slug);
        $payload = $this->validatedPayload($request, $form);

        $captchaPassed = ! $form->require_captcha || $captcha->verify($request->string('captcha_token')->toString() ?: null, $request->ip());

        if (! $captchaPassed) {
            throw ValidationException::withMessages([
                'captcha_token' => 'Não foi possível validar o captcha. Tente novamente.',
            ]);
        }

        $submissions->handle($form, $payload, $request, $captchaPassed);

        return Inertia::render('public/lead-forms/Show', [
            'leadForm' => $this->publicForm($form),
            'submitted' => true,
            'confirmationMessage' => $form->confirmation_message ?: 'Obrigado. Recebemos o seu pedido e entraremos em contacto em breve.',
        ]);
    }

    private function activeForm(string $slug): LeadForm
    {
        return LeadForm::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('active', true)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function publicForm(LeadForm $form): array
    {
        return [
            'name' => $form->name,
            'slug' => $form->slug,
            'description' => $form->description,
            'fields' => $form->fields,
            'require_captcha' => $form->require_captcha,
            'captcha_driver' => config('captcha.driver'),
            'turnstile_site_key' => config('captcha.turnstile.site_key'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, LeadForm $form): array
    {
        $rules = [
            'source_url' => ['nullable', 'url', 'max:2048'],
            'captcha_token' => ['nullable', 'string', 'max:2048'],
        ];
        $labels = [];

        foreach ($form->fields as $field) {
            $key = $field['key'];
            $fieldRules = [];

            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            $fieldRules[] = 'string';
            $fieldRules[] = $field['type'] === LeadForm::FIELD_TEXTAREA ? 'max:5000' : 'max:255';

            if ($field['type'] === LeadForm::FIELD_EMAIL) {
                $fieldRules[] = 'email';
            }

            if ($field['type'] === LeadForm::FIELD_SELECT && ! empty($field['options'])) {
                $fieldRules[] = 'in:'.implode(',', array_map(fn ($option) => str_replace(',', '\,', (string) $option), $field['options']));
            }

            $rules[$key] = $fieldRules;
            $labels[$key] = $field['label'];
        }

        $validated = Validator::make($request->all(), $rules, [], $labels)->validate();

        return collect($form->fields)
            ->mapWithKeys(fn (array $field) => [
                $field['key'] => isset($validated[$field['key']]) ? trim((string) $validated[$field['key']]) : null,
            ])
            ->all();
    }
}
