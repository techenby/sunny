<x-dashboard>
    <livewire:tiles.clock position="a1:b1" />
    <livewire:tiles.status position="a2" email="andymnewhouse@pm.me" />
    <livewire:tiles.status position="b2" email="ashnewhouse24@proton.me" />
    <livewire:tiles.monthly-calendar position="a3:a4" />
    <livewire:tiles.weather position="c1:c2" name="plainfield" />
    <livewire:tiles.coworkers position="b3:b4" name="coworkers-andy" />
    <livewire:tiles.calendar position="c3:c4" :name="['andy', 'andy-work']" label="Andy" />
    <livewire:tiles.calendar position="d1:d2" name="family" label="Family" />
    <livewire:tiles.calendar position="d3:d4" name="ashar" label="Ashar" />
</x-dashboard>
