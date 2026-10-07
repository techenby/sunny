<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;

#[Description("A quick briefing for the day: today's routines and who still has steps left, today's calendar events, and open to-dos.")]
class MorningCheckIn extends Prompt
{
    public function handle(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $today = $team->today();

        return Response::text(implode("\n", [
            "Give me a morning check-in for the {$team->name} household. Today is {$today->format('l, F j, Y')} ({$team->timezone}).",
            '',
            '1. Call get-routine-board for today. For each routine, say whose it is and whether it is done; list the steps still left on any routine that is not complete.',
            '2. Call get-calendar-events with days 1 for today\'s events, in time order. Mention any feed warnings.',
            '3. Call list-checklists with type "todo", then get-checklist for each list with open items, and list what is still open.',
            '',
            'Keep it short and skimmable: a heading per section, and skip any section with nothing in it. End with the one or two things that most need attention today.',
        ]));
    }
}
