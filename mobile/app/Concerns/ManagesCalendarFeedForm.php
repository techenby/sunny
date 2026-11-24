<?php

namespace App\Concerns;

use App\Http\Integrations\Sunny\SunnyCalendar;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use Illuminate\Support\Str;
use Native\Mobile\Attributes\Computed;
use Throwable;

trait ManagesCalendarFeedForm
{
    use DescribesCalendarFeedFailures;
    use NeedsConnection;

    public string $name = '';

    public string $url = '';

    public int $colorIndex = 0;

    public string $error = '';

    public bool $saving = false;

    public ?string $clientUuid = null;

    /**
     * @return list<array{hex: string, label: string}>
     */
    #[Computed]
    public function colorOptions(): array
    {
        return collect(SunnyCalendar::COLORS)
            ->map(fn (string $label, string $hex): array => ['hex' => $hex, 'label' => $label])
            ->values()
            ->all();
    }

    public function chooseColor(int $index): void
    {
        if (array_key_exists($index, $this->colorOptions)) {
            $this->colorIndex = $index;
        }
    }

    protected function fillColor(string $hex): void
    {
        $index = array_search(strtolower($hex), array_keys(SunnyCalendar::COLORS), strict: true);
        $this->colorIndex = $index === false ? 0 : $index;
    }

    protected function submitFeed(?int $id = null): void
    {
        if ($this->saving) {
            return;
        }

        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $team = app(SunnyTeam::class)->current();

        if ($team === null) {
            $this->error = SunnySyncCoordinator::NO_TEAM_ADVICE;

            return;
        }

        $payload = [
            'name' => trim($this->name),
            'url' => $this->feedUrl(),
            'color' => $this->colorOptions[$this->colorIndex]['hex'],
        ];

        if ($id === null) {
            $payload['client_uuid'] = $this->clientUuid ??= (string) Str::uuid();
        }

        $this->saving = true;

        try {
            app(SunnyCalendar::class)->saveFeed($team, $payload, $id);
        } catch (Throwable $exception) {
            $this->error = $this->feedFailureMessage($exception, 'save it');

            return;
        } finally {
            $this->saving = false;
        }

        $this->back();
    }

    protected function feedUrl(): string
    {
        return preg_replace('~^webcals?://~i', 'https://', trim($this->url));
    }

    protected function validationError(): string
    {
        $name = trim($this->name);
        $url = $this->feedUrl();

        return match (true) {
            $name === '' => 'Give the calendar a name.',
            mb_strlen($name) > 255 => 'The name is too long (255 characters max).',
            $url === '' => 'Paste the calendar’s link.',
            ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) => 'The link needs to be a web address starting with https://.',
            default => '',
        };
    }
}
