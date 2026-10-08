@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<column ref="screen" class="{{ Card::classes() }} w-full">
    <text ref="before">Before</text>
    <x-ui.heading ref="heading">{{ $name }}</x-ui.heading>
    <x-ui.heading ref="heading-xl" size="xl" accent>Page title</x-ui.heading>
    <x-ui.heading ref="heading-destructive" class="text-theme-destructive">Download failed</x-ui.heading>
    <x-ui.subheading ref="subheading">Everything in the garage</x-ui.subheading>
    <x-ui.text ref="text" size="sm" max-lines="1">in Garage</x-ui.text>
    <x-ui.text ref="text-strong" variant="strong">Strong</x-ui.text>
    <x-ui.text ref="text-red" color="red">Red</x-ui.text>
    <x-ui.badge ref="badge" color="lime" size="sm">Shopping</x-ui.badge>
    <x-ui.avatar ref="avatar-icon" size="sm" color="amber" :ios="Ios::Cube" :android="Android::ViewInAr" />
    <x-ui.avatar ref="avatar-photo" size="sm" src="https://example.com/photo.jpg" alt="Photo of tent" />
    <x-ui.avatar ref="avatar-initials" circle :name="$name" />
    <text ref="after">After</text>
</column>
