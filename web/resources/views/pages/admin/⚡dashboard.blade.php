<?php

use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component {
    public const WINDOW_DAYS = 30;

    public function with(): array
    {
        $since = CarbonImmutable::now()->subDays(self::WINDOW_DAYS);

        return [
            'userCount' => User::count(),
            'teamCount' => Team::count(),
            'newUserCount' => User::where('created_at', '>=', $since)->count(),
            'weeklyActiveCount' => User::where('last_active_at', '>=', now()->subDays(7))->count(),
            'monthlyActiveCount' => User::where('last_active_at', '>=', $since)->count(),
            'signups' => $signups = $this->signups($since),
            'signupTicks' => $this->wholeNumberTicks(collect($signups)->max('signups')),
            'mostActiveTeams' => $this->mostActiveTeams($since),
            'recentlyActiveTeams' => $this->recentlyActiveTeams(),
            'featureAdoption' => $this->featureAdoption(),
        ];
    }

    /** @return array<int, array{date: string, signups: int}> */
    protected function signups(CarbonImmutable $since): array
    {
        $counts = User::where('created_at', '>=', $since->startOfDay())
            ->pluck('created_at')
            ->countBy(fn ($createdAt) => $createdAt->toDateString());

        return collect(range(self::WINDOW_DAYS, 0))
            ->map(function (int $daysAgo) use ($counts) {
                $date = now()->subDays($daysAgo)->toDateString();

                return ['date' => $date, 'signups' => $counts->get($date, 0)];
            })
            ->all();
    }

    /** @return array<int, int> */
    protected function wholeNumberTicks(int $max): array
    {
        $step = max(1, (int) ceil($max / 4));

        return range(0, max($step, (int) ceil($max / $step) * $step), $step);
    }

    protected function mostActiveTeams(CarbonImmutable $since): Collection
    {
        $sources = [
            'item' => DB::table('items')->where('created_at', '>=', $since),
            'recipe' => DB::table('recipes')->where('created_at', '>=', $since),
            'list' => DB::table('checklists')->where('created_at', '>=', $since),
            'routine' => DB::table('routines')->where('created_at', '>=', $since),
            'completed step' => DB::table('routine_occurrence_steps')
                ->join('routine_occurrences', 'routine_occurrences.id', '=', 'routine_occurrence_steps.routine_occurrence_id')
                ->join('routines', 'routines.id', '=', 'routine_occurrences.routine_id')
                ->where('routine_occurrence_steps.completed_at', '>=', $since),
        ];

        $teamColumn = fn (string $source) => $source === 'completed step' ? 'routines.team_id' : 'team_id';

        $breakdowns = collect($sources)
            ->map(fn ($query, string $source) => $query
                ->selectRaw($teamColumn($source) . ' as team_id, count(*) as aggregate')
                ->groupBy($teamColumn($source))
                ->pluck('aggregate', 'team_id'));

        $teamIds = $breakdowns->flatMap(fn (Collection $counts) => $counts->keys())->unique();

        $memberCounts = Team::whereIn('id', $teamIds)->withCount('members')->pluck('members_count', 'id');

        return $teamIds
            ->filter(fn (int $teamId) => $memberCounts->has($teamId))
            ->map(fn (int $teamId) => [
                'id' => $this->teamIdentifier($teamId),
                'members' => $memberCounts->get($teamId),
                'breakdown' => $breakdowns->map(fn (Collection $counts) => (int) $counts->get($teamId, 0)),
            ])
            ->map(fn (array $team) => [...$team, 'total' => $team['breakdown']->sum()])
            ->sortByDesc('total')
            ->take(10)
            ->values();
    }

    protected function recentlyActiveTeams(): Collection
    {
        return DB::table('team_members')
            ->join('users', 'users.id', '=', 'team_members.user_id')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->whereNull('teams.deleted_at')
            ->selectRaw('team_members.team_id, max(users.last_active_at) as last_active_at, count(*) as members')
            ->groupBy('team_members.team_id')
            ->havingRaw('max(users.last_active_at) is not null')
            ->latest('last_active_at')
            ->limit(10)
            ->get()
            ->map(fn (object $row) => [
                'id' => $this->teamIdentifier($row->team_id),
                'members' => (int) $row->members,
                'last_active_at' => CarbonImmutable::parse($row->last_active_at),
            ]);
    }

    protected function featureAdoption(): Collection
    {
        $teamCount = Team::count();

        return collect([
            'Inventory' => DB::table('items')->whereNull('items.deleted_at'),
            'Recipes' => DB::table('recipes')->whereNull('recipes.deleted_at'),
            'Lists' => DB::table('checklists')->whereNull('checklists.deleted_at'),
            'Routines' => DB::table('routines')->whereNull('routines.deleted_at'),
            'Calendar feeds' => DB::table('calendar_feeds'),
            'Kiosks' => DB::table('kiosk_devices')->whereNotNull('kiosk_devices.paired_at'),
        ])
            ->map(fn ($query) => $query
                ->join('teams', 'teams.id', '=', $query->from . '.team_id')
                ->whereNull('teams.deleted_at')
                ->distinct()
                ->count($query->from . '.team_id'))
            ->map(fn (int $teams) => [
                'teams' => $teams,
                'percent' => $teamCount > 0 ? (int) round($teams / $teamCount * 100) : 0,
            ])
            ->sortByDesc('teams');
    }

    protected function teamIdentifier(int $teamId): string
    {
        return 'T-' . substr(hash_hmac('sha256', 'team:' . $teamId, (string) config('app.key')), 0, 6);
    }
}; ?>

<section class="w-full">
    <x-slot:title>{{ __('Admin Dashboard') }}</x-slot:title>

    <div class="flex flex-col gap-6">
        <flux:heading size="xl">{{ __('Admin Dashboard') }}</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:card>
                <flux:text>{{ __('Users') }}</flux:text>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($userCount) }}</div>
                <flux:text class="mt-1">{{ __(':count new in the last 30 days', ['count' => number_format($newUserCount)]) }}</flux:text>
            </flux:card>

            <flux:card>
                <flux:text>{{ __('Teams') }}</flux:text>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($teamCount) }}</div>
            </flux:card>

            <flux:card>
                <flux:text>{{ __('Active this week') }}</flux:text>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($weeklyActiveCount) }}</div>
                <flux:text class="mt-1">{{ __('users') }}</flux:text>
            </flux:card>

            <flux:card>
                <flux:text>{{ __('Active in the last 30 days') }}</flux:text>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($monthlyActiveCount) }}</div>
                <flux:text class="mt-1">{{ __('users') }}</flux:text>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg">{{ __('Signups') }}</flux:heading>
            <flux:text class="mt-1">{{ __('New users per day, last 30 days') }}</flux:text>

            <flux:chart :value="$signups" class="mt-4 aspect-[4/1] min-h-40">
                <flux:chart.svg>
                    <flux:chart.bar field="signups" class="text-sky-500 dark:text-sky-400" />

                    <flux:chart.axis axis="x" field="date" :format="['month' => 'short', 'day' => 'numeric']">
                        <flux:chart.axis.tick />
                        <flux:chart.axis.line />
                    </flux:chart.axis>

                    <flux:chart.axis axis="y" :tick-values="$signupTicks">
                        <flux:chart.axis.grid />
                        <flux:chart.axis.tick />
                    </flux:chart.axis>

                    <flux:chart.cursor />
                </flux:chart.svg>

                <flux:chart.tooltip>
                    <flux:chart.tooltip.heading field="date" :format="['month' => 'short', 'day' => 'numeric', 'year' => 'numeric']" />
                    <flux:chart.tooltip.value field="signups" :label="__('Signups')" />
                </flux:chart.tooltip>
            </flux:chart>
        </flux:card>

        <div class="grid gap-4 lg:grid-cols-2">
            <flux:card>
                <flux:heading size="lg">{{ __('Most active teams') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Things created and routine steps completed, last 30 days') }}</flux:text>

                @if ($mostActiveTeams->isEmpty())
                    <flux:text class="mt-4">{{ __('No activity yet.') }}</flux:text>
                @else
                    <flux:table class="mt-2">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Team') }}</flux:table.column>
                            <flux:table.column>{{ __('Breakdown') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($mostActiveTeams as $team)
                                <flux:table.row wire:key="active-{{ $team['id'] }}">
                                    <flux:table.cell>
                                        <span class="font-mono">{{ $team['id'] }}</span>
                                        <flux:text size="sm">{{ trans_choice(':count member|:count members', $team['members']) }}</flux:text>
                                    </flux:table.cell>
                                    <flux:table.cell class="whitespace-normal">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($team['breakdown']->filter() as $source => $count)
                                                <flux:badge size="sm">{{ $count }} {{ Str::plural($source, $count) }}</flux:badge>
                                            @endforeach
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            <flux:card>
                <flux:heading size="lg">{{ __('Recently active teams') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Most recent activity by any member, excluding kiosks') }}</flux:text>

                @if ($recentlyActiveTeams->isEmpty())
                    <flux:text class="mt-4">{{ __('No activity yet.') }}</flux:text>
                @else
                    <flux:table class="mt-2">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Team') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Last active') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($recentlyActiveTeams as $team)
                                <flux:table.row wire:key="recent-{{ $team['id'] }}">
                                    <flux:table.cell>
                                        <span class="font-mono">{{ $team['id'] }}</span>
                                        <flux:text size="sm">{{ trans_choice(':count member|:count members', $team['members']) }}</flux:text>
                                    </flux:table.cell>
                                    <flux:table.cell align="end" title="{{ $team['last_active_at']->toDayDateTimeString() }}">
                                        {{ $team['last_active_at']->diffForHumans() }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg">{{ __('Feature adoption') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Share of teams using each feature') }}</flux:text>

            <div class="mt-4 flex flex-col gap-3">
                @foreach ($featureAdoption as $feature => $adoption)
                    <div wire:key="feature-{{ $feature }}">
                        <div class="flex justify-between text-sm">
                            <span class="font-medium text-zinc-900 dark:text-white">{{ __($feature) }}</span>
                            <flux:text size="sm">{{ trans_choice(':count team|:count teams', $adoption['teams']) }} · {{ $adoption['percent'] }}%</flux:text>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <div class="h-full rounded-full bg-sky-500 dark:bg-sky-400" style="width: {{ $adoption['percent'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>
</section>
