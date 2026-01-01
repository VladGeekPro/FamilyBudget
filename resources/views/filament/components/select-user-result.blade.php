<div class="flex rounded-md relative" style="{{ $bgColor ?? '' }}">
    <div class="flex">
        @if ($image)
        <div class="flex items-center">
            <div class="h-10 w-10">
                <img src="{{ url('/storage/'.$image.'') }}" alt="{{ $name }}" role="img" class="h-full w-full rounded-full overflow-hidden shadow object-cover" />
            </div>
        </div>
        @endif

        <div class="flex flex-col justify-center {{ $image ? 'pl-2' : 'p-2' }}">
            <p class="text-sm">{{ $name }}</p>
        </div>
    </div>
</div>