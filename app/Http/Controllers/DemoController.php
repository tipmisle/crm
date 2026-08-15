<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\BloomAndCrumbSeeder;
use Database\Seeders\FotoStudioLunaSeeder;
use Database\Seeders\StudioNolaSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DemoController extends Controller
{
    /**
     * Per-variant workspace setup. Each demo run creates a brand new,
     * isolated Workspace + User — never a shared demo account.
     *
     * @var array<string, array{name: string, slug: string, orders_enabled: bool, appointments_enabled: bool, seeder: class-string}>
     */
    private const VARIANTS = [
        'services' => [
            'name' => 'Studio Nola',
            'slug' => 'studio-nola-demo',
            'orders_enabled' => false,
            'appointments_enabled' => true,
            'seeder' => StudioNolaSeeder::class,
        ],
        'orders' => [
            'name' => 'Sladka delavnica',
            'slug' => 'sladka-delavnica-demo',
            'orders_enabled' => true,
            'appointments_enabled' => false,
            'seeder' => BloomAndCrumbSeeder::class,
        ],
        'both' => [
            'name' => 'Foto studio Luna',
            'slug' => 'foto-studio-luna-demo',
            'orders_enabled' => true,
            'appointments_enabled' => true,
            'seeder' => FotoStudioLunaSeeder::class,
        ],
    ];

    public function show(): Response|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Marketing/Demo');
    }

    public function create(string $variant): RedirectResponse
    {
        if (! isset(self::VARIANTS[$variant])) {
            throw ValidationException::withMessages(['variant' => 'Neveljaven tip demo posla.']);
        }

        // If the visitor already has a session (e.g. they tried a demo
        // earlier, or are logged into a real account), log them out first.
        // Otherwise workspace-scoped queries during seeding below would be
        // filtered by the OLD current_workspace_id (BelongsToWorkspace's
        // global scope keys off Auth::user()), corrupting the new demo's
        // data with null relations.
        if (Auth::check()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        $config = self::VARIANTS[$variant];

        $user = User::create([
            'name' => 'Demo uporabnik',
            'email' => 'demo+'.Str::uuid().'@belezka.demo',
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
            'is_demo' => true,
        ]);

        $workspace = Workspace::create([
            'name' => $config['name'],
            'slug' => $config['slug'].'-'.Str::random(8),
            'timezone' => 'Europe/Ljubljana',
            'currency' => 'EUR',
            'orders_enabled' => $config['orders_enabled'],
            'appointments_enabled' => $config['appointments_enabled'],
            'is_demo' => true,
            'demo_variant' => $variant,
            'demo_expires_at' => now()->addHours(4),
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $user->update(['current_workspace_id' => $workspace->id]);

        (new $config['seeder']($workspace, $user))->run();

        $this->connectSocialChannels($workspace);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    /**
     * Demo channels are seeded as "not_connected" (same as the dev seeders).
     * Flip Instagram + Facebook Messenger to "connected" so the demo Inbox
     * behaves like a real, working workspace — replies are still always
     * routed to the mock provider (see MessagingProviderManager::forChannel).
     */
    private function connectSocialChannels(Workspace $workspace): void
    {
        Channel::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->whereIn('type', ['instagram', 'facebook_messenger'])
            ->update(['status' => 'connected', 'connected_at' => now()]);
    }
}
