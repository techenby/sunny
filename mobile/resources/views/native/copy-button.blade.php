@use('App\Icons\Android')
@use('App\Icons\Ios')

<x-ui.button
    :ref="$ref"
    variant="secondary"
    class="w-full"
    :ios="$copied ? Ios::Checkmark : Ios::DocOnDoc"
    :android="$copied ? Android::Check : Android::ContentCopy"
    :a11y-label="$copied ? ucfirst($label).' copied' : 'Copy '.$label"
    :tap="$action"
>{{ $copied ? 'Copied' : 'Copy '.$label }}</x-ui.button>
