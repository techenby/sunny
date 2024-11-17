<x-dashboard>
    <livewire:tiles.clock position="a1:b1" />
    <livewire:tiles.status position="a2" email="andymnewhouse@proton.me" />
    <livewire:tiles.status position="b2" email="ashnewhouse24@proton.me" />
    <livewire:tiles.monthly-calendar position="a3:a4" />
    <livewire:tiles.weather position="c1:c2" />
    <livewire:tiles.coworkers position="b3:b4" />
    <livewire:tiles.calendar position="c3:c4" :links="config('dashboard.tiles.calendar.andy')" label="Andy" />
    <livewire:tiles.calendar position="d1:d2" :links="config('dashboard.tiles.calendar.family')" label="Family" />
</x-dashboard>
