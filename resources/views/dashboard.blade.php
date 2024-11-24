<x-app-layout>
    @push('head')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                nowIndicator: true,
                scrollTime: '8:00:00',
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: 'payPeriod,timeGridWeek,dayGridMonth'
                },
                views: {
                    payPeriod: {
                        buttonText: 'pay period',
                        type: 'dayGrid',
                        visibleRange: {
                            start: '{{ $payPeriod["start"] }}',
                            end: '{{ $payPeriod["end"] }}',
                        }
                    }
                },
            });
            calendar.render();
        });
    </script>
    @endpush
    <flux:main class="space-y-6">
        <div id="calendar"></div>
    </flux:main>
</x-app-layout>
