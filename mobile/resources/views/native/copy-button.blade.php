@use('App\Icons\Android')
@use('App\Icons\Ios')

<pressable
    ref="{{ $ref }}"
    :a11y-label="$copied ? ucfirst($label).' copied' : 'Copy '.$label"
    class="h-11 w-11 items-center justify-center"
    @tap="{{ $action }}"
>
    <icon
        :ios="$copied ? Ios::Checkmark : Ios::DocOnDoc"
        :android="$copied ? Android::Check : Android::ContentCopy"
        :size="18"
        class="text-theme-primary"
    />
</pressable>
