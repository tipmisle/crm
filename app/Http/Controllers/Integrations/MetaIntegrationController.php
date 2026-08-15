<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Channel;
use App\Services\Messaging\DTOs\DiscoveredAccount;
use App\Services\Messaging\MetaMessagingProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaIntegrationController extends Controller
{
    private const SESSION_STATE_KEY = 'meta_oauth_state';

    private const SESSION_ACCOUNTS_KEY = 'meta_pending_accounts';

    public function connect(Request $request, MetaMessagingProvider $provider): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put(self::SESSION_STATE_KEY, $state);

        return redirect()->away($provider->getAuthorizationUrl($state));
    }

    public function callback(Request $request, MetaMessagingProvider $provider): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::SESSION_STATE_KEY);

        if ($request->query('error')) {
            return redirect()->route('settings.edit')
                ->with('error', 'Povezava z Meta je bila preklicana.');
        }

        if (! $expectedState || $request->query('state') !== $expectedState) {
            Log::warning('meta.oauth.state_mismatch');

            return redirect()->route('settings.edit')
                ->with('error', 'Preverjanje povezave z Meta ni uspelo. Poskusi znova.');
        }

        $code = $request->query('code');
        if (! $code) {
            return redirect()->route('settings.edit')->with('error', 'Manjka avtorizacijska koda Meta.');
        }

        $workspaceId = $request->user()->current_workspace_id;

        try {
            $integration = $provider->handleOAuthCallback((string) $workspaceId, $code);
            $accounts = $provider->listConnectableAccounts($integration);
        } catch (\Throwable $e) {
            Log::error('meta.oauth.callback_failed', ['error' => $e->getMessage()]);

            return redirect()->route('settings.edit')
                ->with('error', 'Povezava z Meta ni uspela. Poskusi znova.');
        }

        if (empty($accounts)) {
            return redirect()->route('settings.edit')
                ->with('error', 'Na tvojem Meta računu ni najdenih strani ali Instagram poslovnih profilov.');
        }

        $request->session()->put(self::SESSION_ACCOUNTS_KEY, [
            'integration_id' => $integration->id,
            'accounts' => array_map(fn (DiscoveredAccount $a) => $a->toSessionArray(), $accounts),
        ]);

        return redirect()->route('settings.edit');
    }

    public function store(Request $request, MetaMessagingProvider $provider): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_ACCOUNTS_KEY);

        if (! $pending) {
            return redirect()->route('settings.edit')->with('error', 'Ni čakajočih Meta računov za povezavo.');
        }

        $data = $request->validate([
            'external_account_ids' => 'required|array|min:1',
            'external_account_ids.*' => 'string',
        ]);

        $workspace = $request->user()->currentWorkspace;
        $selected = collect($pending['accounts'])
            ->whereIn('external_account_id', $data['external_account_ids']);

        // Product rule: one Meta channel/account may belong to only one
        // workspace at a time (see the DB-level unique index on
        // channels.(type, external_account_id) and
        // MessageIngestionService, which would otherwise have no reliable
        // way to route an inbound webhook to the right workspace). Reject
        // the whole request rather than silently connecting some accounts
        // and reassigning/skipping others.
        $alreadyClaimed = $selected->filter(function (array $accountData) use ($workspace) {
            $account = DiscoveredAccount::fromSessionArray($accountData);

            return Channel::withoutGlobalScopes()
                ->where('type', $account->channelType->value)
                ->where('external_account_id', $account->externalAccountId)
                ->where('status', 'connected')
                ->where('workspace_id', '!=', $workspace->id)
                ->exists();
        });

        if ($alreadyClaimed->isNotEmpty()) {
            $names = $alreadyClaimed->map(fn (array $a) => $a['display_name'])->implode(', ');

            return redirect()->route('settings.edit')->with(
                'error',
                "Račun(i) {$names} so že povezani z drugim delovnim prostorom Beležke. En Meta račun je lahko naenkrat povezan samo z enim delovnim prostorom."
            );
        }

        $connected = [];

        foreach ($selected as $accountData) {
            $account = DiscoveredAccount::fromSessionArray($accountData);

            $channel = Channel::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'type' => $account->channelType->value,
                    'external_account_id' => $account->externalAccountId,
                ],
                [
                    'integration_id' => $pending['integration_id'],
                    'display_name' => $account->displayName,
                    'handle' => $account->username ? '@'.$account->username : null,
                    'status' => 'connected',
                    'connected_at' => now(),
                    'last_synced_at' => now(),
                    'access_token' => $account->pageAccessToken,
                    'metadata' => $account->parentPageId ? ['parent_page_id' => $account->parentPageId] : null,
                ]
            );

            $subscribed = $provider->subscribeWebhooks($channel);

            if (! $subscribed) {
                Log::warning('meta.channel.subscribe_failed', ['channel_id' => $channel->id]);
            }

            ActivityLog::record(
                'channel_connected',
                "Kanal {$channel->display_name} ({$account->channelType->label()}) je bil povezan",
                $channel
            );

            $connected[] = $channel->display_name;
        }

        $request->session()->forget(self::SESSION_ACCOUNTS_KEY);

        return redirect()->route('settings.edit')
            ->with('success', 'Povezano: '.implode(', ', $connected).'.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_ACCOUNTS_KEY);

        return redirect()->route('settings.edit');
    }

    public function destroy(Request $request, Channel $channel, MetaMessagingProvider $provider): RedirectResponse
    {
        if (! in_array($channel->type, [ChannelType::Instagram, ChannelType::FacebookMessenger], true)) {
            abort(404);
        }

        $provider->unsubscribeWebhooks($channel);

        $integration = $channel->integration;

        // Clearing external_account_id frees the account to be connected to
        // a different workspace later — see the migration adding
        // unique(type, external_account_id) on channels for why this must
        // happen ("one Meta account, one workspace, at a time").
        $channel->update([
            'status' => 'disconnected',
            'access_token' => null,
            'external_account_id' => null,
        ]);

        ActivityLog::record('channel_disconnected', "Kanal {$channel->display_name} je bil odklopljen", $channel);

        if ($integration && $integration->channels()->where('status', 'connected')->doesntExist()) {
            $integration->update([
                'status' => 'disconnected',
                'access_token' => null,
                'refresh_token' => null,
            ]);
        }

        return redirect()->route('settings.edit')->with('success', 'Kanal je bil odklopljen.');
    }
}
