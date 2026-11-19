<?php

namespace App\Concerns;

use App\Http\Integrations\Sunny\SunnyCalendar;
use App\Http\Integrations\Sunny\SunnyTeam;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Native\Mobile\Attributes\Computed;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Throwable;
use UnexpectedValueException;

trait ManagesCalendarFeedForm
{
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
            $this->error = 'Select a team on the dashboard. If none are listed, sync with Sunny first.';

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

    protected function feedFailureMessage(Throwable $exception, string $action): string
    {
        return match (true) {
            $exception instanceof FatalRequestException => 'You’re offline. Calendars can only be changed with a connection.',
            $exception instanceof RequestException => match ($exception->getResponse()->status()) {
                422 => collect($exception->getResponse()->json('errors'))->flatten()->first() ?? 'Check the form and try again.',
                403 => 'You can’t change this team’s calendars.',
                404 => 'This calendar has already been removed.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'Sunny couldn’t '.$action.' right now. Try again in a moment.',
            },
            $exception instanceof JsonException, $exception instanceof ValidationException, $exception instanceof UnexpectedValueException => 'Sunny sent back something unexpected. Try again in a moment.',
            $exception instanceof AuthenticationException => 'Sign in to Sunny again to change calendars.',
            $exception instanceof RuntimeException => 'Unable to read your saved login. Unlock your device and try again.',
            default => throw $exception,
        };
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
