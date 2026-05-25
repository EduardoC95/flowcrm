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

        $entity = Entity::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Acme Portugal',
            'email' => 'hello@acme.test',
        ]);

        Person::factory()->forEntity($entity)->create([
            'name' => 'Maria Silva',
            'email' => 'maria@acme.test',
            'job_title' => 'Commercial Director',
        ]);

        $deal = Deal::factory()->forEntity($entity)->create([
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
