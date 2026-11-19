@use('App\Icons\Android')
@use('App\Icons\Ios')

<row ref="list-link-error" class="w-full items-start gap-1.5 px-3 pb-1">
    <icon :ios="Ios::ExclamationmarkCircleFill" :android="Android::Error" :size="14" class="text-theme-primary-text" />
    <text class="flex-1 text-xs text-theme-primary-text">{{ $linkError }}</text>
</row>
