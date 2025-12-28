<div class="flex rounded-md relative" style="{{ $bgColor ?? '' }}">
    <div class="flex">
        <div class="flex items-center {{ $bgColor ? '' : 'px-2'}}">
            <div class="h-10 w-10">
                @if(str_contains($image, '.'))
                <img src="{{ url('/storage/'.$image.'') }}" alt="{{ $name }}" role="img" class="h-full w-full rounded-full overflow-hidden shadow object-cover" />
                @else
                <div class="flex justify-center items-center h-full text-lg">{{ $image }}</div>
                @endif
            </div>
        </div>

        <div class="flex flex-col justify-center {{ $bgColor ? '' : 'pl-3'}}">
            <p class="text-sm">{{ $name }}</p>
        </div>
    </div>
</div>