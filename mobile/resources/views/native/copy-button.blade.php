@use('App\Icons\Android')
@use('App\Icons\Ios')

<pressable
    ref="{{ $ref }}"
    :a11y-label="$copied ? ucfirst($label).' copied' : 'Copy '.$label"
    class="min-h-12 min-w-[130] grow items-center justify-center rounded-full border border-theme-outline bg-theme-surface px-4"
    @tap="{{ $action }}"
>
    <row class="items-center gap-2">
        <icon
            :ios="$copied ? Ios::Checkmark : Ios::DocOnDoc"
            :android="$copied ? Android::Check : Android::ContentCopy"
            :size="18"
            class="text-theme-primary-text"
        />
        <text font="semibold" class="text-base text-theme-on-surface">{{ $copied ? 'Copied' : 'Copy '.$label }}</text>
    </row>
</pressable>
