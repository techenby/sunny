@use('App\Icons\Android')
@use('App\Icons\Ios')

<x-ui.button
    :ref="$ref"
    variant="outline-pill"
    class="w-full"
    gap="2"
    :ios="$copied ? Ios::Checkmark : Ios::DocOnDoc"
    :android="$copied ? Android::Check : Android::ContentCopy"
    :icon-size="18"
    icon-class="text-theme-primary-text"
    :a11y-label="$copied ? ucfirst($label).' copied' : 'Copy '.$label"
    :tap="$action"
>{{ $copied ? 'Copied' : 'Copy '.$label }}</x-ui.button>
