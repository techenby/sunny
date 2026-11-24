@props([
    'leading' => 'none',
])

@use('App\Ui\Row')

<divider class="{{ Row::inset($leading) }}" />
