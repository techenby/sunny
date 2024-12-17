<flux:main>
    <div class="flex h-full">
        <div class="w-2/3 space-y-6 pr-2">
            <header class="flex">
                <flux:heading size="xl" level="1">{{ $recipe->name }}</flux:heading>
                <flux:spacer />
                <flux:button size="sm" icon="pencil" icon-variant="outline"></flux:button>
            </header>

            <flux:card>
                <dl class="grid grid-cols-1 sm:grid-cols-3">
                    <div>
                        <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-2">{{ $recipe->prep_time }}</dd>
                        <dt class="text-sm/6 font-medium text-gray-900">Prep</dt>
                    </div>
                    <div>
                        <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-2">{{ $recipe->cook_time }}</dd>
                        <dt class="text-sm/6 font-medium text-gray-900">Cook</dt>
                    </div>
                    <div>
                        <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-2">{{ $recipe->total_time }}</dd>
                        <dt class="text-sm/6 font-medium text-gray-900">Total</dt>
                    </div>
                </dl>
                @if ($recipe->description)
                <flux:separator />
                {!! $recipe->description !!}
                @endif
            </flux:card>


            {!! $recipe->instructions !!}
        </div>
        <div class="w-1/3 space-y-6">
            <div class="h-48 bg-blue-100 rounded-md"></div>
            <flux:card>
                {!! $recipe->ingredients !!}
        </flux:card>
        </div>
    </div>
</flux:main>
