<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'FlowCRM Demo',
            'slug' => 'flowcrm-demo',
            'settings' => [
                'locale' => 'pt',
            ],
        ]);

        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@flowcrm.test',
            'password' => Hash::make('password'),
            'current_tenant_id' => $tenant->id,
        ]);

        $user->tenants()->attach($tenant->id, [
            'role' => Tenant::ROLE_OWNER,
        ]);

        $entities = collect([
            ['name' => 'Acme Portugal', 'vat' => 'PT509999001', 'status' => Entity::STATUS_CLIENT, 'email' => 'hello@acme.test'],
            ['name' => 'Northwind Labs', 'vat' => 'PT509999002', 'status' => Entity::STATUS_ACTIVE, 'email' => 'sales@northwind.test'],
            ['name' => 'Blue Peak Retail', 'vat' => 'PT509999003', 'status' => Entity::STATUS_PROSPECT, 'email' => 'contact@bluepeak.test'],
            ['name' => 'Lisbon Solar Group', 'vat' => 'PT509999004', 'status' => Entity::STATUS_LEAD, 'email' => 'hello@lisbonsolar.test'],
            ['name' => 'Old Harbor Services', 'vat' => 'PT509999005', 'status' => Entity::STATUS_INACTIVE, 'email' => 'office@oldharbor.test'],
        ])->map(fn (array $attributes) => Entity::factory()->create([
            ...$attributes,
            'tenant_id' => $tenant->id,
        ]));

        $people = collect([
            ['entity' => 0, 'name' => 'Maria Silva', 'email' => 'maria@acme.test', 'phone' => '+351 910 100 001', 'position' => 'Commercial Director', 'status' => Person::STATUS_CLIENT],
            ['entity' => 0, 'name' => 'Joao Pereira', 'email' => 'joao@acme.test', 'phone' => '+351 910 100 002', 'position' => 'Operations Manager', 'status' => Person::STATUS_ACTIVE],
            ['entity' => 1, 'name' => 'Ana Costa', 'email' => 'ana@northwind.test', 'phone' => '+351 910 100 003', 'position' => 'Head of Sales', 'status' => Person::STATUS_PROSPECT],
            ['entity' => 2, 'name' => 'Rui Martins', 'email' => 'rui@bluepeak.test', 'phone' => '+351 910 100 004', 'position' => 'Managing Partner', 'status' => Person::STATUS_LEAD],
            ['entity' => null, 'name' => 'Sofia Almeida', 'email' => 'sofia.independent@test', 'phone' => '+351 910 100 005', 'position' => 'Consultant', 'status' => Person::STATUS_ACTIVE],
            ['entity' => null, 'name' => 'Miguel Santos', 'email' => 'miguel.freelance@test', 'phone' => '+351 910 100 006', 'position' => 'Advisor', 'status' => Person::STATUS_INACTIVE],
        ])->map(function (array $attributes) use ($entities, $tenant) {
            $entity = is_int($attributes['entity']) ? $entities->get($attributes['entity']) : null;

            return Person::factory()->create([
                'tenant_id' => $tenant->id,
                'entity_id' => $entity?->id,
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $attributes['phone'],
                'position' => $attributes['position'],
                'job_title' => $attributes['position'],
                'status' => $attributes['status'],
            ]);
        });

        $person = $people->first();

        $deal = Deal::factory()->forPerson($person)->create([
            'title' => 'CRM rollout',
            'stage' => 'qualification',
            'value' => 12500,
        ]);

        CalendarEvent::factory()->forDeal($deal)->create([
            'title' => 'Discovery call',
            'location' => 'Remote',
        ]);
    }
}
